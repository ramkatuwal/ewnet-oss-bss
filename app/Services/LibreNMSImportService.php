<?php

namespace App\Services;

use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\Site;
use App\Models\User;
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
        $sysName = $device['sysName'] ?? $hostname;
        $display = $device['display'] ?? '';
        $ip = $device['ip'] ?? '';
        $os = $device['os'] ?? '';
        $type = $device['type'] ?? '';
        $hardware = $device['hardware'] ?? '';
        $serial = $device['serial'] ?? '';
        $mac = $device['mac'] ?? '';
        $status = $device['status'] ?? null;
        $uptime = $device['uptime'] ?? null;
        $osVersion = $device['version'] ?? $device['os_version'] ?? null;
        $lastPoll = $device['last_poll'] ?? null;

        $existingRef = AssetExternalReference::where('provider', 'librenms')
            ->where('external_id', $deviceId)
            ->first();

        $existingAsset = $existingRef ? Asset::find($existingRef->asset_id) : null;
        if (! $existingAsset && $hostname) {
            $existingAsset = Asset::where('description', $hostname)->orWhere('asset_tag', $hostname)->first();
        }
        if (! $existingAsset && $serial) {
            $existingAsset = Asset::where('serial_number', $serial)->first();
        }
        if (! $existingAsset && $mac) {
            $existingAsset = Asset::whereJsonContains('specifications->mac_address', $mac)->first();
        }
        if (! $existingAsset && $ip) {
            $existingAsset = Asset::whereJsonContains('specifications->ip_address', $ip)->first();
        }

        $siteMapping = $this->siteMapping->mapDevice($device, $integration);

        $action = 'create';
        if ($existingAsset) {
            $action = 'update';
        }
        if ($siteMapping['status'] !== 'mapped') {
            $action = 'skip_unmapped';
        }

        $displayName = $display ?: ($hostname ?: $sysName);

        return [
            'id' => $deviceId,
            'external_id' => $deviceId,
            'name' => $displayName,
            'hostname' => $hostname,
            'ip' => $ip,
            'vendor' => $os,
            'model' => $hardware,
            'type' => $type,
            'status' => $status,
            'site_name' => $siteMapping['site_name'] ?? 'Unmapped',
            'site_id' => $siteMapping['site_id'] ?? null,
            'action' => $action,
            'asset_id' => $existingAsset?->id,
            'evidence' => $existingAsset ? [['field' => 'hostname', 'value' => $hostname, 'strength' => 'strong']] : [],
            'location' => $device['location'] ?? null,
            'lat' => $device['lat'] ?? null,
            'lng' => $device['lng'] ?? null,
            'serial' => $serial,
            'mac' => $mac,
            'os_version' => $osVersion,
            'uptime' => $uptime,
            'last_poll' => $lastPoll,
        ];
    }

    /**
     * Build the provider observation payload from a LibreNMS device record.
     * This is OBSERVED data — it must never overwrite authoritative Asset fields.
     */
    protected function buildObservations(array $fullDevice): array
    {
        return [
            'provider_status' => $fullDevice['status'] ?? null,
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
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        DB::transaction(function () use ($integration, $selectedDevices, &$results) {
            foreach ($selectedDevices as $deviceData) {
                try {
                    $deviceId = $deviceData['external_id'];
                    $fullDevice = $deviceData;
                    $fullDevice['device_id'] = $fullDevice['device_id'] ?? $fullDevice['external_id'] ?? null;

                    $siteMapping = $this->siteMapping->mapDevice($fullDevice, $integration);
                    if ($siteMapping['status'] !== 'mapped') {
                        $results['skipped']++;
                        Log::warning('LibreNMS device import skipped', [
                            'device_id' => $deviceId,
                            'reason' => $siteMapping['message'] ?? 'No matching Site found',
                        ]);

                        continue;
                    }

                    $existingRef = AssetExternalReference::where('provider', 'librenms')
                        ->where('external_id', $deviceId)
                        ->first();

                    $asset = $existingRef ? Asset::find($existingRef->asset_id) : null;

                    if (! $asset) {
                        $hostname = $fullDevice['hostname'] ?? '';
                        $serial = $fullDevice['serial'] ?? '';
                        $mac = $fullDevice['mac'] ?? '';
                        $ip = $fullDevice['ip'] ?? '';

                        if ($hostname) {
                            $asset = Asset::where('description', $hostname)->orWhere('asset_tag', $hostname)->first();
                        }
                        if (! $asset && $serial) {
                            $asset = Asset::where('serial_number', $serial)->first();
                        }
                        if (! $asset && $mac) {
                            $asset = Asset::whereJsonContains('specifications->mac_address', $mac)->first();
                        }
                        if (! $asset && $ip) {
                            $asset = Asset::whereJsonContains('specifications->ip_address', $ip)->first();
                        }
                    }

                    $displayName = ($fullDevice['display'] ?? null)
                        ?: ($fullDevice['hostname'] ?? null)
                        ?: ($fullDevice['sysName'] ?? null);

                    $observations = $this->buildObservations($fullDevice);

                    if ($asset) {
                        // RE-SYNC: refresh provider observations ONLY.
                        // Must NOT overwrite authoritative Asset identity fields.
                        $asset->update([
                            'specifications' => array_merge($asset->specifications ?? [], [
                                'last_synced' => now(),
                            ] + $observations),
                        ]);
                        $results['updated']++;
                    } else {
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
                            'manufacturer' => $fullDevice['os'] ?? null,
                            'model' => $fullDevice['hardware'] ?? null,
                            'category' => 'NETWORK',
                            'type' => $fullDevice['type'] ?? 'device',
                            'status' => 'OPERATIONAL',
                            'condition' => 'GOOD',
                            'quantity' => 1,
                            'unit' => 'pcs',
                            'serial_number' => $fullDevice['serial'] ?? null,
                            'specifications' => array_merge([
                                'source' => 'librenms',
                                'external_id' => $deviceId,
                                'serial_number' => $fullDevice['serial'] ?? null,
                                'mac_address' => $fullDevice['mac'] ?? null,
                                'ip_address' => $fullDevice['ip'] ?? null,
                            ], $observations),
                        ]);

                        AssetExternalReference::create([
                            'asset_id' => $newAsset->id,
                            'provider' => 'librenms',
                            'external_type' => 'device',
                            'external_id' => $deviceId,
                            'metadata' => ['imported_at' => now()],
                        ]);
                        $results['created']++;
                    }
                } catch (\Exception $e) {
                    Log::error('LibreNMS device import failed', ['device_id' => $deviceData['external_id'] ?? 'unknown', 'error' => $e->getMessage()]);
                    $results['failed']++;
                }
            }
        });

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
