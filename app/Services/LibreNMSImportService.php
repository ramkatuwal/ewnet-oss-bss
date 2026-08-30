<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Integration;
use App\Models\Site;
use App\Models\User;
use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibreNMSImportService
{
    protected SiteMappingService $siteMapping;
    protected AuditService $audit;

    public function __construct(SiteMappingService $siteMapping, AuditService $audit)
    {
        $this->siteMapping = $siteMapping;
        $this->audit = $audit;
    }

    /**
     * Fetch devices from LibreNMS
     */
    public function fetchDevices(Integration $integration, array $params = []): array
    {
        $client = new LibreNMSClient($integration);
        $result = $client->get('/devices', $params);

        if ($result['status'] !== 200 || empty($result['data'])) {
            return ['devices' => [], 'error' => 'Failed to fetch devices from LibreNMS'];
        }

        $devices = $result['data']['devices'] ?? [];
        return ['devices' => $devices, 'count' => count($devices)];
    }

    /**
     * Preview import without writing to database
     */
    public function preview(Integration $integration, User $user): array
    {
        $result = $this->fetchDevices($integration);
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $devices = $result['devices'];
        $preview = [];

        foreach ($devices as $device) {
            $item = $this->analyzeDevice($device, $user);
            $preview[] = $item;
        }

        return [
            'total' => count($preview),
            'preview' => $preview,
            'summary' => $this->summarizePreview($preview),
        ];
    }

    /**
     * Analyze a single device for preview
     */
    protected function analyzeDevice(array $device, User $user): array
    {
        $deviceId = (string) ($device['device_id'] ?? '');
        $hostname = $device['hostname'] ?? '';
        $status = $device['status'] ?? '';

        // Check if device already imported
        $existingAsset = $this->findExistingAsset($deviceId);

        // Map to Site
        $siteMapping = $this->siteMapping->mapDevice($device, $user, $device['integration_id'] ?? null);

        return [
            'device_id' => $deviceId,
            'hostname' => $hostname,
            'status' => $status,
            'exists' => $existingAsset !== null,
            'existing_asset_id' => $existingAsset?->id,
            'existing_asset_tag' => $existingAsset?->asset_tag,
            'site_mapped' => $siteMapping['status'] === 'mapped',
            'site_id' => $siteMapping['site_id'] ?? null,
            'site_message' => $siteMapping['message'] ?? 'No matching site',
            'action' => $this->determineAction($existingAsset, $siteMapping),
            'device_data' => $device,
        ];
    }

    /**
     * Find existing asset by LibreNMS device_id stored in specifications
     */
    protected function findExistingAsset(string $deviceId): ?Asset
    {
        return Asset::where('specifications->external_id', $deviceId)
            ->orWhere('specifications->librenms_device_id', $deviceId)
            ->first();
    }

    /**
     * Determine action for a device
     */
    protected function determineAction(?Asset $existingAsset, array $siteMapping): string
    {
        if ($siteMapping['status'] !== 'mapped') {
            return 'skip_unmapped';
        }
        if ($existingAsset) {
            return 'update';
        }
        return 'create';
    }

    /**
     * Summarize preview results
     */
    protected function summarizePreview(array $preview): array
    {
        $summary = [
            'create' => 0,
            'update' => 0,
            'skip_unmapped' => 0,
            'skip_duplicate' => 0,
            'total' => count($preview),
        ];

        foreach ($preview as $item) {
            $action = $item['action'] ?? 'skip_unmapped';
            if (isset($summary[$action])) {
                $summary[$action]++;
            }
        }

        return $summary;
    }

    /**
     * Execute import
     */
    public function import(Integration $integration, User $user, array $options = []): array
    {
        $result = $this->fetchDevices($integration);
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $devices = $result['devices'];
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'unmapped' => 0,
            'errors' => [],
            'details' => [],
        ];

        foreach ($devices as $device) {
            $item = $this->processDevice($device, $user, $integration, $options);
            $results['details'][] = $item;

            if ($item['status'] === 'created') {
                $results['created']++;
            } elseif ($item['status'] === 'updated') {
                $results['updated']++;
            } elseif ($item['status'] === 'skipped') {
                $results['skipped']++;
            } elseif ($item['status'] === 'unmapped') {
                $results['unmapped']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $item['message'] ?? 'Unknown error';
            }
        }

        $this->audit->log('librenms.import.completed', 'success', null, [
            'integration_id' => $integration->id,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Process a single device for import
     */
    protected function processDevice(array $device, User $user, Integration $integration, array $options): array
    {
        $deviceId = (string) ($device['device_id'] ?? '');

        if (empty($deviceId)) {
            return ['status' => 'failed', 'message' => 'Missing device_id'];
        }

        // Map to Site
        $siteMapping = $this->siteMapping->mapDevice($device, $integration);

        if ($siteMapping['status'] !== 'mapped') {
            return [
                'status' => 'unmapped',
                'device_id' => $deviceId,
                'hostname' => $device['hostname'] ?? '',
                'message' => $siteMapping['message'] ?? 'No matching site',
            ];
        }

        $site = Site::find($siteMapping['site_id']);
        if (!$site) {
            return [
                'status' => 'failed',
                'device_id' => $deviceId,
                'message' => 'Site not found in database',
            ];
        }

        // Check if dry-run
        if ($options['dry_run'] ?? false) {
            return [
                'status' => 'preview',
                'device_id' => $deviceId,
                'hostname' => $device['hostname'] ?? '',
                'site_id' => $site->id,
                'action' => $this->findExistingAsset($deviceId) ? 'update' : 'create',
            ];
        }

        // Create or update Asset
        return DB::transaction(function () use ($device, $site, $deviceId) {
            $existing = $this->findExistingAsset($deviceId);

            $assetData = $this->mapDeviceToAsset($device, $site);

            if ($existing) {
                $existing->update($assetData);
                $this->audit->log('asset.updated.from_librenms', 'success', $existing, [
                    'device_id' => $deviceId,
                    'integration_id' => $site->integration_id ?? null,
                ]);
                return ['status' => 'updated', 'asset_id' => $existing->id, 'hostname' => $device['hostname'] ?? ''];
            } else {
                $asset = Asset::create($assetData);
                $this->audit->log('asset.created.from_librenms', 'success', $asset, [
                    'device_id' => $deviceId,
                    'integration_id' => $site->integration_id ?? null,
                ]);
                return ['status' => 'created', 'asset_id' => $asset->id, 'hostname' => $device['hostname'] ?? ''];
            }
        });
    }

    /**
     * Map LibreNMS device data to Asset model
     */
    protected function mapDeviceToAsset(array $device, Site $site): array
    {
        $status = $this->mapDeviceStatus($device['status'] ?? '');
        $hostname = $device['hostname'] ?? '';
        $sysName = $device['sysName'] ?? '';

        return [
            'site_id' => $site->id,
            'asset_tag' => $hostname ?: $sysName,
            'type' => $device['type'] ?? 'network_device',
            'category' => 'NETWORK',
            'manufacturer' => $device['hardware'] ?? null,
            'model' => $device['hardware'] ?? null,
            'serial_number' => $device['serial'] ?? null,
            'status' => $status,
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
            'specifications' => [
                'librenms_device_id' => (string) ($device['device_id'] ?? ''),
                'external_id' => (string) ($device['device_id'] ?? ''),
                'os' => $device['os'] ?? null,
                'version' => $device['version'] ?? null,
                'ip' => $device['ip'] ?? null,
                'location' => $device['location'] ?? null,
                'sysName' => $sysName,
                'sysDescr' => $device['sysDescr'] ?? null,
                'hardware' => $device['hardware'] ?? null,
                'imported_from' => 'librenms',
                'imported_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Map LibreNMS device status to EWNET Asset status
     */
    protected function mapDeviceStatus(string $libreNmsStatus): string
    {
        return match ($libreNmsStatus) {
            '1' => 'OPERATIONAL',
            '0' => 'MAINTENANCE',
            default => 'OPERATIONAL',
        };
    }
}
