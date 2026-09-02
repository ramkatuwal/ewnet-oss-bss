<?php

namespace App\Services\Integrations\Uisp;

use App\Integrations\Providers\Uisp\UispClient;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Integration;
use App\Models\SiteExternalReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UispDeviceSyncService
{
    protected Integration $integration;
    protected UispClient $client;
    protected array $counts = [
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
        'failed' => 0,
        'site_mapping_failed' => 0,
        'processed' => 0,
    ];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->client = new UispClient($integration);
    }

    public function execute(): array
    {
        Log::info('Starting UISP Device Synchronization', ['integration_id' => $this->integration->id]);

        try {
            $uispDevices = $this->client->getDevices();
            Log::info('Fetched devices from UISP', ['count' => count($uispDevices)]);

            foreach ($uispDevices as $index => $uispDevice) {
                try {
                    $this->syncSingleDevice($uispDevice);
                } catch (\Throwable $e) {
                    Log::error('Failed to sync single device', [
                        'index' => $index,
                        'device_id' => $uispDevice['id'] ?? $uispDevice['identification']['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $this->counts['failed']++;
                }
            }

            $this->counts['processed'] = 
                $this->counts['created'] + 
                $this->counts['updated'] + 
                $this->counts['unchanged'] + 
                $this->counts['skipped'] + 
                $this->counts['failed'];

            Log::info('UISP Device Synchronization Completed', $this->counts);
            return $this->counts;
        } catch (\Throwable $e) {
            Log::error('UISP Device Synchronization Failed', [
                'error' => $e->getMessage(),
                'integration_id' => $this->integration->id,
            ]);
            throw $e;
        }
    }

    protected function syncSingleDevice(array $uispDevice): void
    {
        $identification = $uispDevice['identification'] ?? [];
        $externalId = $uispDevice['id'] ?? $identification['id'] ?? null;
        
        if (!$externalId) {
            $this->counts['failed']++;
            Log::warning('UISP device missing ID', ['device' => $uispDevice]);
            return;
        }

        // Skip devices without required data
        if (empty($identification['name'] ?? null) && empty($identification['model'] ?? null)) {
            $this->counts['skipped']++;
            Log::warning('UISP device skipped: missing required data', ['external_id' => $externalId]);
            return;
        }

        $reference = AssetExternalReference::where('provider', 'uisp')
            ->where('external_type', 'device')
            ->where('external_id', $externalId)
            ->first();

        $assetData = $this->mapDeviceData($uispDevice);

        // Resolve Site ID
        $uispSiteId = $identification['site']['id'] ?? null;
        $siteId = $this->resolveSiteId($uispSiteId);
        
        if (!$siteId) {
            $this->counts['site_mapping_failed']++;
            $this->counts['skipped']++;
            Log::warning('UISP device skipped: site not mapped', [
                'external_id' => $externalId,
                'uisp_site_id' => $uispSiteId,
            ]);
            return;
        }
        $assetData['site_id'] = $siteId;

        DB::transaction(function () use ($reference, $assetData, $externalId) {
            if ($reference) {
                $asset = $reference->asset;
                if (!$asset) {
                    // Orphan reference — recreate
                    $this->counts['skipped']++;
                    Log::warning('Orphan asset external reference', ['external_id' => $externalId]);
                    return;
                }
                if ($this->hasChanges($asset, $assetData)) {
                    $asset->update($this->getUispOwnedFields($asset, $assetData));
                    $this->counts['updated']++;
                    Log::debug('UISP device updated', ['external_id' => $externalId, 'asset_id' => $asset->id]);
                } else {
                    $this->counts['unchanged']++;
                }
            } else {
                // Generate a unique asset_tag
                $assetTag = 'UISP-' . substr($externalId, 0, 8);
                $baseTag = $assetTag;
                $counter = 1;
                while (Asset::where('asset_tag', $assetTag)->exists()) {
                    $assetTag = $baseTag . '-' . $counter++;
                }
                $assetData['asset_tag'] = $assetTag;

                $asset = Asset::create($assetData);
                AssetExternalReference::create([
                    'asset_id' => $asset->id,
                    'provider' => 'uisp',
                    'external_type' => 'device',
                    'external_id' => $externalId,
                ]);
                $this->counts['created']++;
                Log::info('UISP device created', ['external_id' => $externalId, 'asset_id' => $asset->id]);
            }
        });
    }

    protected function mapDeviceData(array $uispDevice): array
    {
        $identification = $uispDevice['identification'] ?? [];
        $status = match ($uispDevice['status'] ?? $identification['status'] ?? null) {
            'online' => 'OPERATIONAL',
            'offline' => 'MAINTENANCE',
            default => 'SPARE',
        };

        $specifications = [
            'mac_address' => $identification['mac'] ?? null,
            'firmware_version' => $identification['firmwareVersion'] ?? null,
            'ip_address' => $uispDevice['overview']['ipAddress'] ?? null,
            'synced_from' => 'uisp',
            'synced_at' => now()->toISOString(),
        ];

        return [
            'model' => $identification['model'] ?? $identification['modelName'] ?? null,
            'manufacturer' => $identification['vendor'] ?? $identification['vendorName'] ?? 'Ubiquiti',
            'serial_number' => $identification['serialNumber'] ?? null,
            'type' => 'Network Device',
            'category' => 'NETWORK',
            'status' => $status,
            'specifications' => $specifications,
            'description' => $identification['name'] ?? $identification['displayName'] ?? null,
        ];
    }

    /**
     * Get only fields that UISP is allowed to update.
     * Preserves manual EWNET-managed fields.
     */
    protected function getUispOwnedFields(Asset $asset, array $newData): array
    {
        $allowed = ['model', 'manufacturer', 'serial_number', 'type', 'category', 'status', 'description'];
        $updates = [];

        foreach ($allowed as $field) {
            if (isset($newData[$field]) && $asset->{$field} != $newData[$field]) {
                $updates[$field] = $newData[$field];
            }
        }

        // Merge specifications (preserve existing keys)
        $existingSpecs = $asset->specifications ?? [];
        $newSpecs = $newData['specifications'] ?? [];
        $mergedSpecs = array_merge($existingSpecs, $newSpecs);
        $updates['specifications'] = $mergedSpecs;

        return $updates;
    }

    protected function resolveSiteId(?string $uispSiteId): ?int
    {
        if (!$uispSiteId) return null;

        $siteRef = SiteExternalReference::where('provider', 'uisp')
            ->where('external_type', 'site')
            ->where('external_id', $uispSiteId)
            ->first();

        return $siteRef?->site_id;
    }

    protected function hasChanges(Asset $asset, array $newData): bool
    {
        $allowed = ['model', 'manufacturer', 'serial_number', 'type', 'category', 'status', 'description'];
        foreach ($allowed as $field) {
            if (isset($newData[$field]) && $asset->{$field} != $newData[$field]) {
                return true;
            }
        }

        // Check specifications changes (only for UISP keys)
        $existingSpecs = $asset->specifications ?? [];
        $newSpecs = $newData['specifications'] ?? [];
        $uispKeys = ['mac_address', 'firmware_version', 'ip_address', 'synced_from', 'synced_at'];
        foreach ($uispKeys as $key) {
            if (($existingSpecs[$key] ?? null) != ($newSpecs[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
