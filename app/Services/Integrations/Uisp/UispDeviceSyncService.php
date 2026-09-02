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
        $externalId = $uispDevice['id'] ?? $uispDevice['identification']['id'] ?? null;
        if (!$externalId) {
            $this->counts['failed']++;
            return;
        }

        $reference = AssetExternalReference::where('provider', 'uisp')
            ->where('external_type', 'device')
            ->where('external_id', $externalId)
            ->first();

        $assetData = $this->mapDeviceData($uispDevice);

        $siteId = $this->resolveSiteId($uispDevice['identification']['site']['id'] ?? null);
        if (!$siteId) {
            $this->counts['site_mapping_failed']++;
            $this->counts['failed']++;
            return;
        }
        $assetData['site_id'] = $siteId;

        DB::transaction(function () use ($reference, $assetData, $externalId) {
            if ($reference) {
                $asset = $reference->asset;
                if ($this->hasChanges($asset, $assetData)) {
                    $asset->update($assetData);
                    $this->counts['updated']++;
                } else {
                    $this->counts['unchanged']++;
                }
            } else {
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
        foreach ($newData as $key => $value) {
            if ($key === 'specifications') {
                if (json_encode($asset->specifications) !== json_encode($value)) return true;
            } elseif ($asset->{$key} != $value) {
                return true;
            }
        }
        return false;
    }
}
