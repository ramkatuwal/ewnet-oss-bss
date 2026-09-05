<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\ImportHistory;
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
        $ip = $device['ip'] ?? '';
        $os = $device['os'] ?? '';
        $type = $device['type'] ?? '';
        $hardware = $device['hardware'] ?? '';

        // Check for existing asset via external reference or hostname/IP
        $existingRef = AssetExternalReference::where('provider', 'librenms')
            ->where('external_id', $deviceId)
            ->first();
        
        $existingAsset = $existingRef ? Asset::find($existingRef->asset_id) : null;
        if (!$existingAsset && $hostname) {
            $existingAsset = Asset::where('description', $hostname)->orWhere('asset_tag', $hostname)->first();
        }

        $siteMapping = $this->siteMapping->mapDevice($device, $integration);

        $action = 'create';
        if ($existingAsset) $action = 'update';
        if ($siteMapping['status'] !== 'mapped') $action = 'skip_unmapped';

        return [
            'id' => $deviceId,
            'external_id' => $deviceId,
            'name' => $sysName,
            'hostname' => $hostname,
            'ip' => $ip,
            'vendor' => $os,
            'model' => $hardware,
            'type' => $type,
            'site_name' => $siteMapping['site_name'] ?? 'Unmapped',
            'site_id' => $siteMapping['site_id'] ?? null,
            'action' => $action,
            'asset_id' => $existingAsset?->id,
            'evidence' => $existingAsset ? [['field' => 'hostname', 'value' => $hostname, 'strength' => 'strong']] : [],
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

        DB::transaction(function () use ($integration, $user, $selectedDevices, &$results, $history) {
            foreach ($selectedDevices as $deviceData) {
                try {
                    $deviceId = $deviceData['external_id'];
                    $client = new LibreNMSClient($integration);
                    // Fetch full details for this specific device if needed, or use what we have
                    $fullDevice = $deviceData; 

                    $siteMapping = $this->siteMapping->mapDevice($fullDevice, $integration);
                    if ($siteMapping['status'] !== 'mapped') {
                        $results['skipped']++;
                        continue;
                    }

                    $existingRef = AssetExternalReference::where('provider', 'librenms')
                        ->where('external_id', $deviceId)
                        ->first();
                    
                    $asset = $existingRef ? Asset::find($existingRef->asset_id) : null;

                    if ($asset) {
                        $asset->update([
                            'description' => $fullDevice['sysName'] ?? $fullDevice['hostname'],
                            'manufacturer' => $fullDevice['os'] ?? null,
                            'model' => $fullDevice['hardware'] ?? null,
                            'site_id' => $siteMapping['site_id'],
                            'specifications' => array_merge($asset->specifications ?? [], ['last_synced' => now()]),
                        ]);
                        $results['updated']++;
                    } else {
                        $assetTag = 'LNM-' . substr($deviceId, 0, 8);
                        $baseTag = $assetTag;
                        $counter = 1;
                        while (Asset::where('asset_tag', $assetTag)->exists()) {
                            $assetTag = $baseTag . '-' . $counter++;
                        }

                        $newAsset = Asset::create([
                            'site_id' => $siteMapping['site_id'],
                            'asset_tag' => $assetTag,
                            'description' => $fullDevice['sysName'] ?? $fullDevice['hostname'],
                            'manufacturer' => $fullDevice['os'] ?? null,
                            'model' => $fullDevice['hardware'] ?? null,
                            'category' => 'NETWORK',
                            'type' => $fullDevice['type'] ?? 'device',
                            'status' => 'OPERATIONAL',
                            'condition' => 'GOOD',
                            'quantity' => 1,
                            'unit' => 'pcs',
                            'specifications' => ['source' => 'librenms', 'external_id' => $deviceId],
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
            if (isset($summary[$action])) $summary[$action]++;
        }
        return $summary;
    }
}
