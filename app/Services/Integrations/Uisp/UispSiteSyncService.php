<?php

namespace App\Services\Integrations\Uisp;

use App\Integrations\Providers\Uisp\UispClient;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteExternalReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UispSiteSyncService
{
    protected Integration $integration;
    protected UispClient $client;
    protected array $counts = [
        'created' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
        'failed' => 0,
        'processed' => 0,
    ];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->client = new UispClient($integration);
    }

    public function execute(): array
    {
        Log::info('Starting UISP Site Synchronization', ['integration_id' => $this->integration->id]);

        try {
            $uispSites = $this->client->getSites();
            Log::info('Fetched sites from UISP', ['count' => count($uispSites)]);

            foreach ($uispSites as $uispSite) {
                $this->syncSingleSite($uispSite);
            }

            $this->counts['processed'] = 
                $this->counts['created'] + 
                $this->counts['updated'] + 
                $this->counts['unchanged'] + 
                $this->counts['skipped'] + 
                $this->counts['failed'];

            Log::info('UISP Site Synchronization Completed', $this->counts);
            return $this->counts;
        } catch (\Throwable $e) {
            Log::error('UISP Site Synchronization Failed', [
                'error' => $e->getMessage(),
                'integration_id' => $this->integration->id,
            ]);
            throw $e;
        }
    }

    protected function syncSingleSite(array $uispSite): void
    {
        $externalId = $uispSite['id'] ?? null;
        if (!$externalId) {
            $this->counts['failed']++;
            Log::warning('UISP site missing ID', ['site' => $uispSite]);
            return;
        }

        // Skip sites without required data
        if (empty($uispSite['name'] ?? null)) {
            $this->counts['skipped']++;
            Log::warning('UISP site skipped: missing name', ['external_id' => $externalId]);
            return;
        }

        $reference = SiteExternalReference::where('provider', 'uisp')
            ->where('external_type', 'site')
            ->where('external_id', $externalId)
            ->first();

        $siteData = $this->mapSiteData($uispSite);

        DB::transaction(function () use ($reference, $siteData, $externalId, $uispSite) {
            if ($reference) {
                $site = $reference->site;
                if (!$site) {
                    // Orphan reference — recreate
                    $this->counts['skipped']++;
                    Log::warning('Orphan site external reference', ['external_id' => $externalId]);
                    return;
                }
                if ($this->hasChanges($site, $siteData)) {
                    // Only update fields owned by UISP
                    $site->update($this->getUispOwnedFields($site, $siteData));
                    $this->counts['updated']++;
                    Log::debug('UISP site updated', ['external_id' => $externalId, 'site_id' => $site->id]);
                } else {
                    $this->counts['unchanged']++;
                }
            } else {
                // Generate a unique site_code
                $siteCode = $siteData['site_code'] ?? 'UISP-' . substr($externalId, 0, 8);
                $baseCode = $siteCode;
                $counter = 1;
                while (Site::where('site_code', $siteCode)->exists()) {
                    $siteCode = $baseCode . '-' . $counter++;
                }
                $siteData['site_code'] = $siteCode;

                $site = Site::create($siteData);
                SiteExternalReference::create([
                    'site_id' => $site->id,
                    'provider' => 'uisp',
                    'external_type' => 'site',
                    'external_id' => $externalId,
                ]);
                $this->counts['created']++;
                Log::info('UISP site created', ['external_id' => $externalId, 'site_id' => $site->id]);
            }
        });
    }

    protected function mapSiteData(array $uispSite): array
    {
        $location = $uispSite['location'] ?? [];
        $address = $uispSite['address'] ?? [];

        $status = match ($uispSite['status'] ?? null) {
            'active' => 'active',
            'inactive' => 'inactive',
            default => 'planned',
        };

        $siteData = [
            'name' => $uispSite['name'] ?? 'Unknown Site',
            'type' => 'pop',
            'status' => $status,
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lon'] ?? null,
            'address' => $address['fullAddress'] ?? null,
            'metadata' => [
                'uisp_parent_id' => $uispSite['parent'] ?? null,
                'uisp_device_count' => $uispSite['deviceCount'] ?? 0,
                'synced_from' => 'uisp',
                'synced_at' => now()->toISOString(),
            ],
        ];

        // Preserve existing metadata if updating
        return $siteData;
    }

    /**
     * Get only fields that UISP is allowed to update.
     * Preserves manual EWNET-managed fields.
     */
    protected function getUispOwnedFields(Site $site, array $newData): array
    {
        $allowed = ['name', 'status', 'latitude', 'longitude', 'address'];
        $updates = [];

        foreach ($allowed as $field) {
            if (isset($newData[$field]) && $site->{$field} != $newData[$field]) {
                $updates[$field] = $newData[$field];
            }
        }

        // Merge metadata (preserve existing keys)
        $existingMetadata = $site->metadata ?? [];
        $newMetadata = $newData['metadata'] ?? [];
        $mergedMetadata = array_merge($existingMetadata, $newMetadata);
        $updates['metadata'] = $mergedMetadata;

        return $updates;
    }

    protected function hasChanges(Site $site, array $newData): bool
    {
        $allowed = ['name', 'status', 'latitude', 'longitude', 'address'];
        foreach ($allowed as $field) {
            if (isset($newData[$field]) && $site->{$field} != $newData[$field]) {
                return true;
            }
        }

        // Check metadata changes (only for UISP keys)
        $existingMeta = $site->metadata ?? [];
        $newMeta = $newData['metadata'] ?? [];
        $uispKeys = ['uisp_parent_id', 'uisp_device_count', 'synced_from', 'synced_at'];
        foreach ($uispKeys as $key) {
            if (($existingMeta[$key] ?? null) != ($newMeta[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
