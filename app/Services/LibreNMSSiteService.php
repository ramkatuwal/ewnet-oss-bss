<?php

namespace App\Services;

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

    /**
     * Fetch LibreNMS locations/sites
     */
    public function fetchLocations(Integration $integration): array
    {
        $client = new LibreNMSClient($integration);
        $result = $client->get('/locations');

        if ($result['status'] !== 200 || empty($result['data'])) {
            return ['locations' => [], 'error' => 'Failed to fetch locations from LibreNMS'];
        }

        $locations = $result['data']['locations'] ?? [];
        return ['locations' => $locations, 'count' => count($locations)];
    }

    /**
     * Fetch devices with location info (alternative approach if locations API is limited)
     */
    public function fetchDevicesWithLocations(Integration $integration): array
    {
        $client = new LibreNMSClient($integration);
        $result = $client->get('/devices', ['type' => 'all']);

        if ($result['status'] !== 200 || empty($result['data'])) {
            return ['devices' => [], 'error' => 'Failed to fetch devices from LibreNMS'];
        }

        $devices = $result['data']['devices'] ?? [];
        
        // Extract unique locations
        $locations = [];
        foreach ($devices as $device) {
            if (!empty($device['location'])) {
                $locations[$device['location']] = [
                    'name' => $device['location'],
                    'device_count' => ($locations[$device['location']]['device_count'] ?? 0) + 1,
                    'devices' => array_merge(
                        $locations[$device['location']]['devices'] ?? [],
                        [[
                            'device_id' => $device['device_id'],
                            'hostname' => $device['hostname'],
                        ]]
                    ),
                ];
            }
        }

        return [
            'locations' => array_values($locations),
            'count' => count($locations),
            'devices' => $devices,
        ];
    }

    /**
     * Preview site mapping
     */
    public function previewSites(Integration $integration, User $user): array
    {
        $result = $this->fetchDevicesWithLocations($integration);
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $locations = $result['locations'];
        $preview = [];

        foreach ($locations as $location) {
            $locationName = $location['name'];
            $mappedSite = $this->findMappedSite($integration, $locationName);
            
            $preview[] = [
                'location_name' => $locationName,
                'device_count' => $location['device_count'],
                'devices' => $location['devices'],
                'mapped' => $mappedSite !== null,
                'site_id' => $mappedSite?->id,
                'site_name' => $mappedSite?->name,
                'site_code' => $mappedSite?->site_code,
                'action' => $mappedSite ? 'update' : 'create',
            ];
        }

        return [
            'total' => count($preview),
            'preview' => $preview,
            'summary' => [
                'mapped' => count(array_filter($preview, fn($p) => $p['mapped'])),
                'unmapped' => count(array_filter($preview, fn($p) => !$p['mapped'])),
            ],
        ];
    }

    /**
     * Find mapped EWNET Site for a LibreNMS location
     */
    protected function findMappedSite(Integration $integration, string $locationName): ?Site
    {
        // Check explicit mapping
        $ref = SiteExternalReference::where('provider', 'librenms')
            ->where('external_type', 'location')
            ->where('external_id', $locationName)
            ->first();

        if ($ref && $ref->site) {
            return $ref->site;
        }

        // Try to find by name match
        return Site::where('name', $locationName)
            ->orWhere('site_code', $locationName)
            ->first();
    }

    /**
     * Map a LibreNMS location to an EWNET Site
     */
    public function mapLocation(Integration $integration, string $locationName, int $siteId, User $user): array
    {
        $site = Site::find($siteId);
        if (!$site) {
            return ['success' => false, 'error' => 'Site not found'];
        }

        // Check management scope
        if (!ManagementScopeService::isInScope($user, $site)) {
            return ['success' => false, 'error' => 'Site is outside your management scope'];
        }

        // Create or update external reference
        $ref = SiteExternalReference::updateOrCreate(
            [
                'provider' => 'librenms',
                'external_type' => 'location',
                'external_id' => $locationName,
            ],
            [
                'site_id' => $siteId,
                'metadata' => [
                    'mapped_by' => $user->id,
                    'mapped_at' => now()->toISOString(),
                    'integration_id' => $integration->id,
                ],
            ]
        );

        $this->audit->log('librenms.site.mapped', 'success', $site, [
            'location_name' => $locationName,
            'external_reference_id' => $ref->id,
            'integration_id' => $integration->id,
        ]);

        return ['success' => true, 'reference' => $ref];
    }

    /**
     * Import sites from LibreNMS locations
     */
    public function importSites(Integration $integration, User $user, array $options = []): array
    {
        $result = $this->fetchDevicesWithLocations($integration);
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $locations = $result['locations'];
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        // If specific locations are provided, filter
        $targetLocations = $options['locations'] ?? null;
        if ($targetLocations) {
            $locations = array_filter($locations, function ($loc) use ($targetLocations) {
                return in_array($loc['name'], $targetLocations);
            });
        }

        foreach ($locations as $location) {
            $locationName = $location['name'];
            
            // Skip if dry-run
            if ($options['dry_run'] ?? false) {
                $results['details'][] = [
                    'location' => $locationName,
                    'status' => 'preview',
                    'action' => 'create',
                ];
                continue;
            }

            // Check if already mapped
            $existingMapping = SiteExternalReference::where('provider', 'librenms')
                ->where('external_type', 'location')
                ->where('external_id', $locationName)
                ->first();

            if ($existingMapping) {
                $results['skipped']++;
                $results['details'][] = [
                    'location' => $locationName,
                    'status' => 'skipped',
                    'action' => 'already_mapped',
                    'site_id' => $existingMapping->site_id,
                ];
                continue;
            }

            // Create site
            try {
                $site = DB::transaction(function () use ($locationName, $user, $integration) {
                    $site = Site::create([
                        'site_code' => $this->generateSiteCode($locationName),
                        'name' => $locationName,
                        'type' => 'pop',
                        'status' => 'active',
                        'company_id' => $this->getDefaultCompany($user),
                    ]);

                    SiteExternalReference::create([
                        'site_id' => $site->id,
                        'provider' => 'librenms',
                        'external_type' => 'location',
                        'external_id' => $locationName,
                        'metadata' => [
                            'created_by' => $user->id,
                            'created_at' => now()->toISOString(),
                            'integration_id' => $integration->id,
                            'device_count' => $location['device_count'],
                        ],
                    ]);

                    return $site;
                });

                $results['created']++;
                $results['details'][] = [
                    'location' => $locationName,
                    'status' => 'created',
                    'site_id' => $site->id,
                    'site_code' => $site->site_code,
                ];

                $this->audit->log('librenms.site.created', 'success', $site, [
                    'location_name' => $locationName,
                    'integration_id' => $integration->id,
                ]);

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'location' => $locationName,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Generate a site code from a location name
     */
    protected function generateSiteCode(string $locationName): string
    {
        // Sanitize: uppercase, remove special chars, limit to 10 chars
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $locationName));
        $code = substr($code, 0, 10);
        
        // Make unique if needed
        $existing = Site::where('site_code', $code)->exists();
        if ($existing) {
            $code = $code . '-' . strtoupper(substr(uniqid(), -4));
        }
        
        return $code;
    }

    /**
     * Get the default company for a user
     */
    protected function getDefaultCompany(User $user): ?int
    {
        // Use user's company if available
        if ($user->company_id) {
            return $user->company_id;
        }

        // Fallback to first company in user's management scope
        $scopes = ManagementScopeService::getEffectiveScopes($user);
        foreach ($scopes as $scope) {
            if ($scope['scope_type'] === 'company') {
                return $scope['scope_id'];
            }
        }

        return null;
    }
}
