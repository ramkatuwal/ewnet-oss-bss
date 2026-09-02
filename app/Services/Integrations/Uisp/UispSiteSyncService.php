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
            return;
        }

        $reference = SiteExternalReference::where('provider', 'uisp')
            ->where('external_type', 'site')
            ->where('external_id', $externalId)
            ->first();

        $siteData = $this->mapSiteData($uispSite);

        DB::transaction(function () use ($reference, $siteData, $externalId) {
            if ($reference) {
                $site = $reference->site;
                if ($this->hasChanges($site, $siteData)) {
                    $site->update($siteData);
                    $this->counts['updated']++;
                } else {
                    $this->counts['unchanged']++;
                }
            } else {
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

        return [
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
    }

    protected function hasChanges(Site $site, array $newData): bool
    {
        foreach ($newData as $key => $value) {
            if ($key === 'metadata') {
                if (json_encode($site->metadata) !== json_encode($value)) {
                    return true;
                }
            } elseif ($site->{$key} != $value) {
                return true;
            }
        }
        return false;
    }
}
