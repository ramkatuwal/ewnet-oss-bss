<?php

namespace App\Services;

use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use App\Models\Asset;
use App\Models\AssetDeviceType;
use App\Models\AssetExternalReference;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public function fetchDevices(Integration $integration, array $params = []): array
    {
        abort_unless($integration->provider === 'librenms', 422, 'A LibreNMS integration is required.');
        $client = new LibreNMSClient($integration);
        $result = $client->get('/devices', $params);

        if ($result['status'] !== 200 || empty($result['data'])) {
            return ['devices' => [], 'error' => 'Failed to fetch devices from LibreNMS'];
        }

        $devices = $result['data']['devices'] ?? null;
        if (! is_array($devices) || ! array_is_list($devices)) {
            return ['devices' => [], 'error' => 'Invalid LibreNMS devices response'];
        }

        return ['devices' => $devices, 'count' => count($devices)];
    }

    public function preview(Integration $integration, User $user): array
    {
        $result = $this->fetchDevices($integration);
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        $devices = $result['devices'];
        $preview = [];

        foreach ($devices as $device) {
            $preview[] = $this->analyzeDevice($device, $user, $integration);
        }

        return [
            'total' => count($preview),
            'analysis' => $preview,
            'summary' => $this->summarizePreview($preview),
        ];
    }

    protected function analyzeDevice(array $device, User $user, Integration $integration): array
    {
        $deviceId = (string) ($device['device_id'] ?? '');
        $hostname = $device['hostname'] ?? '';
        $ip = $device['ip'] ?? '';
        $os = $device['os'] ?? '';
        $type = $device['type'] ?? '';
        $hardware = $device['hardware'] ?? '';
        $serial = $device['serial'] ?? '';
        $mac = $device['mac'] ?? '';
        $observations = $this->buildObservations($device, $integration);
        $status = $observations['provider_status'];
        $uptime = $device['uptime'] ?? null;
        $osVersion = $device['version'] ?? $device['os_version'] ?? null;
        $lastPoll = $device['last_poll'] ?? null;

        $existingRef = AssetExternalReference::where('provider', 'librenms')
            ->where('integration_id', $integration->id)
            ->where('external_type', 'device')
            ->where('external_id', $deviceId)
            ->first();

        $existingAsset = $existingRef ? Asset::find($existingRef->asset_id) : null;
        if ($existingAsset && (! ManagementScopeService::isInScope($user, $existingAsset)
            || ($integration->company_id !== null && (int) $existingAsset->site?->company_id !== (int) $integration->company_id))) {
            $existingAsset = null;
        }

        $siteMapping = $this->resolveSite($device, $integration, $user);

        $action = 'create';
        if ($existingAsset) {
            $action = 'update';
        }
        if ($siteMapping['status'] !== 'mapped') {
            $action = 'skip_unmapped';
        }

        $displayName = self::deviceName($device);

        return [
            'id' => $deviceId,
            'external_id' => $deviceId,
            'name' => $displayName,
            'hostname' => $hostname,
            'ip' => $ip,
            'vendor' => null,
            'os' => $os,
            'display' => $device['display'] ?? null,
            'sysName' => $device['sysName'] ?? null,
            'model' => $hardware,
            'type' => $type,
            'status' => $status,
            'site_name' => $siteMapping['site_name'] ?? 'Unmapped',
            'site_id' => $siteMapping['site_id'] ?? null,
            'action' => $action,
            'asset_id' => $existingAsset?->id,
            'evidence' => $existingAsset ? [['field' => 'external_id', 'value' => $deviceId, 'strength' => 'strong']] : [],
            'location' => $device['location'] ?? null,
            'lat' => $device['lat'] ?? null,
            'lng' => $device['lng'] ?? null,
            'serial' => $serial,
            'mac' => $mac,
            'os_version' => $osVersion,
            'uptime' => $uptime,
            'last_poll' => $lastPoll,
            'provider_observations' => $observations,
        ];
    }

    public static function deviceName(array $device): ?string
    {
        foreach (['display', 'sysName', 'hostname'] as $field) {
            $value = trim((string) ($device[$field] ?? ''));
            if ($value !== '' && ($field !== 'hostname' || ! filter_var($value, FILTER_VALIDATE_IP))) {
                return $value;
            }
        }

        return null;
    }

    protected function resolveSite(array $device, Integration $integration, User $user): array
    {
        $sites = ManagementScopeService::applyScopeToQuery(Site::query(), $user, Site::class)
            ->when($integration->company_id !== null, fn ($q) => $q->where('company_id', $integration->company_id));
        // Legacy unscoped device references cannot identify a device in this integration.
        $mapped = (clone $sites)->whereHas('externalReferences', fn ($q) => $q
            ->where('provider', 'librenms')->where('external_type', 'device')
            ->where('external_id', (string) ($device['device_id'] ?? ''))
            ->where('metadata->integration_id', $integration->id))->limit(2)->get();
        if ($mapped->isEmpty()) {
            $hostname = trim((string) ($device['hostname'] ?? ''));
            $location = trim((string) ($device['location'] ?? ''));
            $mapped = (clone $sites)->where(function ($q) use ($hostname, $location) {
                $q->whereRaw('false');
                if ($hostname !== '' && ! filter_var($hostname, FILTER_VALIDATE_IP)) {
                    $q->orWhereRaw('LOWER(site_code) = ?', [strtolower($hostname)]);
                }
                if ($location !== '') {
                    $q->orWhere('name', $location);
                }
            })->limit(2)->get();
        }
        $site = $mapped->count() === 1 ? $mapped->first() : null;

        return ['status' => $site ? 'mapped' : 'unmapped', 'site_id' => $site?->id,
            'site_name' => $site?->name, 'message' => 'No unique permitted Site mapping'];
    }

    /**
     * Build the provider observation payload from a LibreNMS device record.
     * This is OBSERVED data — it must never overwrite authoritative Asset fields.
     */
    protected function buildObservations(array $fullDevice, Integration $integration): array
    {
        $providerStatus = $fullDevice['status'] ?? null;
        $normalizedStatus = match ($providerStatus) {
            1, '1', 'UP' => 'UP',
            0, '0', 'DOWN' => 'DOWN',
            default => 'UNKNOWN',
        };

        return [
            'source' => 'librenms',
            'external_id' => (string) ($fullDevice['device_id'] ?? ''),
            'integration_id' => $integration->id,
            'observed_display' => $fullDevice['display'] ?? null,
            'observed_sys_name' => $fullDevice['sysName'] ?? null,
            'provider_type' => $fullDevice['type'] ?? null,
            'ip_address' => $fullDevice['ip'] ?? null,
            'serial_number' => $fullDevice['serial'] ?? null,
            'mac_address' => $fullDevice['mac'] ?? null,
            'last_poll' => $fullDevice['last_poll'] ?? null,
            'provider_status' => $normalizedStatus,
            'observed_hostname' => $fullDevice['hostname'] ?? null,
            'observed_os' => $fullDevice['os'] ?? null,
            'observed_hardware' => $fullDevice['hardware'] ?? null,
            'observed_version' => $fullDevice['os_version'] ?? $fullDevice['version'] ?? null,
            'observed_uptime' => $fullDevice['uptime'] ?? null,
            'last_observed_at' => now()->toIso8601String(),
        ];
    }

    public function execute(Integration $integration, User $user, array $selectedDevices, ImportHistory $history): array
    {
        Gate::forUser($user)->authorize('import', $integration);
        $fresh = $this->fetchDevices($integration);
        if (isset($fresh['error'])) {
            throw new \RuntimeException($fresh['error']);
        }
        $devices = [];
        foreach ($fresh['devices'] as $device) {
            $id = is_array($device) && (is_string($device['device_id'] ?? null) || is_int($device['device_id'] ?? null))
                ? trim((string) $device['device_id']) : '';
            if ($id === '' || array_key_exists($id, $devices)) {
                throw new \RuntimeException('Invalid or duplicate LibreNMS device ID in provider response.');
            }
            $devices[$id] = $device;
        }
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($integration, $user, $selectedDevices, $devices, &$results) {
            // Serialize imports for this integration, including first-time reference creation.
            Integration::whereKey($integration->id)->lockForUpdate()->firstOrFail();
            foreach ($selectedDevices as $deviceData) {
                $deviceId = is_array($deviceData) && is_scalar($deviceData['external_id'] ?? null)
                    ? trim((string) $deviceData['external_id']) : '';
                if ($deviceId === '' || ! array_key_exists($deviceId, $devices)) {
                    $results['failed']++;
                    $results['errors'][] = ['external_id' => $deviceId, 'message' => 'Selected external_id is missing from the fresh provider response.'];

                    continue;
                }
                try {
                    $outcome = DB::transaction(function () use ($integration, $user, $devices, $deviceId) {
                        $fullDevice = $devices[$deviceId];

                        $siteMapping = $this->resolveSite($fullDevice, $integration, $user);
                        if ($siteMapping['status'] !== 'mapped') {
                            Log::warning('LibreNMS device import skipped', [
                                'device_id' => $deviceId,
                                'reason' => $siteMapping['message'] ?? 'No matching Site found',
                            ]);

                            return 'skipped';
                        }

                        $existingRef = AssetExternalReference::where('provider', 'librenms')
                            ->where('integration_id', $integration->id)
                            ->where('external_type', 'device')
                            ->where('external_id', $deviceId)
                            ->first();

                        $asset = $existingRef ? Asset::whereKey($existingRef->asset_id)->lockForUpdate()->firstOrFail() : null;
                        if ($asset && (! ManagementScopeService::isInScope($user, $asset)
                            || ($integration->company_id !== null && (int) $asset->site?->company_id !== (int) $integration->company_id))) {
                            throw new \RuntimeException('Referenced asset is outside the permitted scope.');
                        }

                        $displayName = self::deviceName($fullDevice);
                        $observations = $this->buildObservations($fullDevice, $integration);
                        $ip = filter_var(trim((string) ($fullDevice['ip'] ?? '')), FILTER_VALIDATE_IP) ?: null;

                        if ($asset) {
                            // RE-SYNC: refresh provider observations ONLY.
                            // Must NOT overwrite authoritative Asset identity fields.
                            $syncData = [
                                'specifications' => array_merge($asset->specifications ?? [], [
                                    'last_synced' => now(),
                                ] + $observations),
                            ];
                            if ($ip !== null) {
                                $syncData['management_ip'] = $ip;
                            }
                            $asset->update($syncData);

                            return 'updated';
                        } else {
                            $type = AssetDeviceType::active()->whereIn('code', ['OTHER', 'UNKNOWN'])
                                ->whereHas('category', fn ($q) => $q->where('code', 'NETWORK')->where('is_active', true))
                                ->orderBy('code')->first();
                            if (! $type) {
                                throw new \DomainException('Configure an active NETWORK OTHER or UNKNOWN device type before importing.');
                            }
                            $assetTag = 'LNM-'.substr($deviceId, 0, 8);
                            $baseTag = $assetTag;
                            $counter = 1;
                            while (Asset::where('asset_tag', $assetTag)->exists()) {
                                $assetTag = $baseTag.'-'.$counter++;
                            }

                            $newAsset = Asset::create([
                                'site_id' => $siteMapping['site_id'],
                                'company_id' => Site::find($siteMapping['site_id'])?->company_id,
                                'asset_tag' => $assetTag,
                                'device_name' => $displayName,
                                'management_ip' => $ip,
                                'manufacturer' => null,
                                'model' => $fullDevice['hardware'] ?? null,
                                'category' => 'NETWORK',
                                'type' => $type->code,
                                'status' => 'OPERATIONAL',
                                'condition' => 'GOOD',
                                'quantity' => 1,
                                'unit' => 'pcs',
                                'serial_number' => trim((string) ($fullDevice['serial'] ?? '')) ?: null,
                                'specifications' => $observations,
                            ]);

                            AssetExternalReference::create([
                                'asset_id' => $newAsset->id,
                                'provider' => 'librenms',
                                'external_type' => 'device',
                                'external_id' => $deviceId,
                                'integration_id' => $integration->id,
                                'metadata' => ['imported_at' => now()],
                            ]);

                            return 'created';
                        }
                    });
                    $results[$outcome]++;
                } catch (\Exception $e) {
                    Log::error('LibreNMS device import failed', ['device_id' => $deviceId, 'exception_class' => get_class($e)]);
                    $results['failed']++;
                    $results['errors'][] = ['external_id' => $deviceId, 'message' => $e instanceof \DomainException
                        ? $e->getMessage() : 'Device import failed due to a scope or data integrity conflict.'];
                }
            }
        });

        $history->update(['metadata' => array_merge($history->metadata ?? [], ['device_errors' => $results['errors']])]);
        $this->audit->log('librenms.devices.import', $results['failed'] > 0 ? 'partial' : 'success', $integration, [
            'history_id' => $history->id,
            'created' => $results['created'], 'updated' => $results['updated'],
            'skipped' => $results['skipped'], 'failed' => $results['failed'],
        ]);

        return $results;
    }

    protected function summarizePreview(array $preview): array
    {
        $summary = ['create' => 0, 'update' => 0, 'skip_unmapped' => 0, 'total' => count($preview)];
        foreach ($preview as $item) {
            $action = $item['action'] ?? 'skip_unmapped';
            if (isset($summary[$action])) {
                $summary[$action]++;
            }
        }

        return $summary;
    }
}
