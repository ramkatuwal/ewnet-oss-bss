<?php

namespace App\Services;

use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Models\User;
use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibreNMSSiteService
{
    protected SiteMappingService $siteMapping;
    protected AuditService $audit;

    public function __construct(SiteMappingService $siteMapping, AuditService $audit)
    {
        $this->siteMapping = $siteMapping;
        $this->audit = $audit;
    }

    public function fetchDevicesWithLocations(Integration $integration): array
    {
        $client = new LibreNMSClient($integration);
        $result = $client->get('/devices', ['type' => 'all']);

        if ($result['status'] !== 200 || empty($result['data'])) {
            return ['locations' => [], 'error' => 'Failed to fetch devices from LibreNMS'];
        }

        $devices = $result['data']['devices'] ?? [];
        $locations = [];

        foreach ($devices as $device) {
            if (!empty($device['location'])) {
                $name = $device['location'];
                if (!isset($locations[$name])) {
                    $locations[$name] = ['name' => $name, 'device_count' => 0, 'devices' => []];
                }
                $locations[$name]['device_count']++;
                $locations[$name]['devices'][] = ['device_id' => $device['device_id'], 'hostname' => $device['hostname']];
            }
        }

        return ['locations' => array_values($locations), 'count' => count($locations)];
    }

    public function previewSites(Integration $integration, User $user): array
    {
        $result = $this->fetchDevicesWithLocations($integration);
        if (isset($result['error'])) return ['error' => $result['error']];

        $preview = [];
        foreach ($result['locations'] as $location) {
            $existingRef = SiteExternalReference::where('provider', 'librenms')
                ->where('external_id', $location['name'])
                ->first();
            
            $existingSite = $existingRef ? Site::find($existingRef->site_id) : null;
            
            $preview[] = [
                'id' => $location['name'],
                'external_id' => $location['name'],
                'name' => $location['name'],
                'device_count' => $location['device_count'],
                'action' => $existingSite ? 'update' : 'create',
                'site_id' => $existingSite?->id,
                'evidence' => $existingSite ? [['field' => 'name', 'value' => $location['name'], 'strength' => 'strong']] : [],
            ];
        }

        return [
            'total' => count($preview),
            'analysis' => $preview,
            'summary' => [
                'create' => count(array_filter($preview, fn($p) => $p['action'] === 'create')),
                'update' => count(array_filter($preview, fn($p) => $p['action'] === 'update')),
            ],
        ];
    }

    public function execute(Integration $integration, User $user, array $selectedLocations, ImportHistory $history): array
    {
        $results = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        DB::transaction(function () use ($integration, $user, $selectedLocations, &$results, $history) {
            foreach ($selectedLocations as $locationData) {
                try {
                    $locationName = $locationData['external_id'];
                    
                    $existingRef = SiteExternalReference::where('provider', 'librenms')
                        ->where('external_id', $locationName)
                        ->first();

                    $site = $existingRef ? Site::find($existingRef->site_id) : null;

                    if ($site) {
                        $site->update([
                            'metadata' => array_merge($site->metadata ?? [], ['last_synced' => now(), 'source' => 'librenms']),
                        ]);
                        $results['updated']++;
                    } else {
                        // Create a new site. In a real scenario, we might need to map it to a region/branch.
                        // For now, we create a basic site record.
                        $newSite = Site::create([
                            'site_code' => 'LNM-' . substr(md5($locationName), 0, 8),
                            'name' => $locationName,
                            'type' => 'pop',
                            'status' => 'active',
                            'metadata' => ['source' => 'librenms', 'external_id' => $locationName],
                        ]);

                        SiteExternalReference::create([
                            'site_id' => $newSite->id,
                            'provider' => 'librenms',
                            'external_type' => 'location',
                            'external_id' => $locationName,
                            'metadata' => ['imported_at' => now()],
                        ]);
                        $results['created']++;
                    }
                } catch (\Exception $e) {
                    Log::error('LibreNMS site import failed', ['location' => $locationData['external_id'] ?? 'unknown', 'error' => $e->getMessage()]);
                    $results['failed']++;
                }
            }
        });

        return $results;
    }
}
