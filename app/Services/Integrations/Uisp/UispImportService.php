<?php

namespace App\Services\Integrations\Uisp;

use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Integrations\Providers\Uisp\UispClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UispImportService
{
    protected Integration $integration;
    protected UispClient $client;
    protected UispDuplicateDetector $detector;
    protected ?ImportHistory $history;

    public function __construct(Integration $integration, ?ImportHistory $history = null)
    {
        $this->integration = $integration;
        $this->client = new UispClient($integration);
        $this->detector = new UispDuplicateDetector();
        $this->history = $history;
    }

    public function preview(array $options = []): array
    {
        $sites = $this->client->getSites() ?? [];
        $devices = $this->client->getDevices() ?? [];

        $siteAnalysis = [];
        foreach ($sites as $site) {
            $siteAnalysis[] = $this->detector->analyzeSite($site);
        }

        $deviceAnalysis = [];
        foreach ($devices as $device) {
            $deviceAnalysis[] = $this->detector->analyzeDevice($device);
        }

        return [
            'sites' => [
                'total' => count($sites),
                'analysis' => $siteAnalysis,
                'summary' => $this->summarizeAnalysis($siteAnalysis),
            ],
            'devices' => [
                'total' => count($devices),
                'analysis' => $deviceAnalysis,
                'summary' => $this->summarizeAnalysis($deviceAnalysis),
            ],
        ];
    }

    public function execute(array $selectedRecords): array
    {
        $results = [
            'sites' => ['created' => 0, 'updated' => 0, 'linked' => 0, 'skipped' => 0, 'failed' => 0, 'conflicts' => 0],
            'devices' => ['created' => 0, 'updated' => 0, 'linked' => 0, 'skipped' => 0, 'failed' => 0, 'conflicts' => 0],
            'interfaces' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
            'ips' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0],
        ];

        DB::transaction(function () use ($selectedRecords, &$results) {
            if (!empty($selectedRecords['sites'])) {
                foreach ($selectedRecords['sites'] as $siteData) {
                    $this->processSite($siteData, $results['sites']);
                }
            }

            if (!empty($selectedRecords['devices'])) {
                foreach ($selectedRecords['devices'] as $deviceData) {
                    $this->processDevice($deviceData, $results);
                }
            }
        });

        return $results;
    }

    protected function processSite(array $data, array &$results): void
    {
        $externalId = $data['external_id'] ?? null;
        if (!$externalId) {
            $results['failed']++;
            return;
        }

        try {
            $existingRef = SiteExternalReference::where('provider', 'uisp')
                ->where('external_type', 'site')
                ->where('external_id', (string) $externalId)
                ->first();

            if ($existingRef) {
                $site = Site::find($existingRef->site_id);
                if ($site) {
                    $site->update([
                        'name' => $data['name'] ?? $site->name,
                        'description' => $data['description'] ?? $site->description,
                        'metadata' => array_merge($site->metadata ?? [], ['last_synced' => now()]),
                    ]);
                    $results['updated']++;
                    return;
                }
            }

            $site = Site::create([
                'site_code' => 'UISP-' . substr(md5($externalId), 0, 8),
                'name' => $data['name'] ?? 'UISP Site ' . $externalId,
                'type' => 'pop',
                'status' => 'active',
                'metadata' => ['source' => 'uisp', 'external_id' => $externalId],
            ]);

            SiteExternalReference::create([
                'site_id' => $site->id,
                'provider' => 'uisp',
                'external_type' => 'site',
                'external_id' => (string) $externalId,
                'metadata' => ['imported_at' => now()],
            ]);

            $results['created']++;
        } catch (\Exception $e) {
            Log::error('UISP site import failed', ['external_id' => $externalId, 'error' => $e->getMessage()]);
            $results['failed']++;
        }
    }

    protected function processDevice(array $data, array &$results): void
    {
        $externalId = $data['external_id'] ?? null;
        if (!$externalId) {
            $results['devices']['failed']++;
            return;
        }

        try {
            $existingRef = AssetExternalReference::where('provider', 'uisp')
                ->where('external_type', 'device')
                ->where('external_id', (string) $externalId)
                ->first();

            $asset = null;
            if ($existingRef) {
                $asset = Asset::find($existingRef->asset_id);
                if ($asset) {
                    $asset->update([
                        'asset_tag' => $data['name'] ?? $asset->asset_tag,
                        'model' => $data['model'] ?? $asset->model,
                        'manufacturer' => $data['manufacturer'] ?? $asset->manufacturer,
                        'serial_number' => $data['serial_number'] ?? $asset->serial_number,
                        'specifications' => array_merge($asset->specifications ?? [], ['last_synced' => now()]),
                    ]);
                    $results['devices']['updated']++;
                    $this->processDeviceInterfaces($data, $asset, $results);
                    return;
                }
            }

            $siteId = $this->resolveSiteId($data);
            $assetTag = 'UISP-' . substr($externalId, 0, 8);
            $baseTag = $assetTag;
            $counter = 1;
            while (Asset::where('asset_tag', $assetTag)->exists()) {
                $assetTag = $baseTag . '-' . $counter++;
            }

            $asset = Asset::create([
                'site_id' => $siteId,
                'asset_tag' => $assetTag,
                'serial_number' => $data['serial_number'] ?? null,
                'category' => 'NETWORK',
                'type' => $data['type'] ?? 'device',
                'manufacturer' => $data['manufacturer'] ?? null,
                'model' => $data['model'] ?? null,
                'status' => 'OPERATIONAL',
                'condition' => 'GOOD',
                'quantity' => 1,
                'unit' => 'pcs',
                'specifications' => ['source' => 'uisp', 'external_id' => $externalId],
                'description' => $data['name'] ?? null,
            ]);

            AssetExternalReference::create([
                'asset_id' => $asset->id,
                'provider' => 'uisp',
                'external_type' => 'device',
                'external_id' => (string) $externalId,
                'metadata' => ['imported_at' => now()],
            ]);

            $results['devices']['created']++;
            $this->processDeviceInterfaces($data, $asset, $results);

        } catch (\Exception $e) {
            Log::error('UISP device import failed', ['external_id' => $externalId, 'error' => $e->getMessage()]);
            $results['devices']['failed']++;
        }
    }

    protected function processDeviceInterfaces(array $data, Asset $asset, array &$results): void
    {
        $interfaces = $data['interfaces'] ?? [];
        foreach ($interfaces as $interfaceData) {
            $this->processInterface($interfaceData, $asset, $results);
        }
    }

    protected function processInterface(array $interfaceData, Asset $asset, array &$results): void
    {
        $externalId = $interfaceData['external_id'] ?? null;
        $existing = \App\Models\AssetInterface::where('asset_id', $asset->id)
            ->where('name', $interfaceData['name'])
            ->first();

        if ($existing) {
            $existing->update([
                'display_name' => $interfaceData['display_name'] ?? $existing->display_name,
                'type' => $interfaceData['type'] ?? $existing->type,
                'mac_address' => $interfaceData['mac_address'] ?? $existing->mac_address,
                'speed' => $interfaceData['speed'] ?? $existing->speed,
                'status' => $interfaceData['status'] ?? $existing->status,
                'last_seen_at' => now(),
            ]);
            $results['interfaces']['updated']++;
        } else {
            \App\Models\AssetInterface::create([
                'asset_id' => $asset->id,
                'name' => $interfaceData['name'] ?? 'unknown',
                'display_name' => $interfaceData['display_name'] ?? null,
                'type' => $interfaceData['type'] ?? null,
                'mac_address' => $interfaceData['mac_address'] ?? null,
                'speed' => $interfaceData['speed'] ?? null,
                'status' => $interfaceData['status'] ?? 'active',
                'is_management' => $interfaceData['is_management'] ?? false,
                'provider' => 'uisp',
                'external_type' => 'interface',
                'external_id' => $externalId,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'metadata' => $interfaceData['metadata'] ?? [],
            ]);
            $results['interfaces']['created']++;
        }

        $ips = $interfaceData['ip_addresses'] ?? [];
        foreach ($ips as $ipData) {
            $this->processIp($ipData, $asset, $results);
        }
    }

    protected function processIp(array $ipData, Asset $asset, array &$results): void
    {
        $interfaceName = $ipData['interface_name'] ?? null;
        if (!$interfaceName) {
            $results['ips']['skipped']++;
            return;
        }

        $interface = \App\Models\AssetInterface::where('asset_id', $asset->id)
            ->where('name', $interfaceName)
            ->first();

        if (!$interface) {
            $results['ips']['skipped']++;
            return;
        }

        $existing = \App\Models\IpAddress::where('asset_interface_id', $interface->id)
            ->where('ip_address', $ipData['ip'])
            ->first();

        if ($existing) {
            $existing->update([
                'prefix_length' => $ipData['prefix'] ?? $existing->prefix_length,
                'is_primary' => $ipData['is_primary'] ?? $existing->is_primary,
                'is_management' => $ipData['is_management'] ?? $existing->is_management,
                'last_seen_at' => now(),
            ]);
            $results['ips']['updated']++;
        } else {
            \App\Models\IpAddress::create([
                'asset_interface_id' => $interface->id,
                'ip_address' => $ipData['ip'],
                'prefix_length' => $ipData['prefix'] ?? null,
                'is_primary' => $ipData['is_primary'] ?? false,
                'is_management' => $ipData['is_management'] ?? false,
                'provider' => 'uisp',
                'external_type' => 'ip',
                'external_id' => $ipData['external_id'] ?? null,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'metadata' => $ipData['metadata'] ?? [],
            ]);
            $results['ips']['created']++;
        }
    }

    protected function resolveSiteId(array $data): int
    {
        if (!empty($data['site_id'])) {
            $site = Site::find($data['site_id']);
            if ($site) return $site->id;
        }

        if (!empty($data['site_external_id'])) {
            $ref = SiteExternalReference::where('provider', 'uisp')
                ->where('external_id', (string) $data['site_external_id'])
                ->first();
            if ($ref) return $ref->site_id;
        }

        $site = Site::create([
            'site_code' => 'UISP-DEFAULT-' . time(),
            'name' => 'UISP Default Site',
            'type' => 'pop',
            'status' => 'active',
        ]);
        return $site->id;
    }

    protected function summarizeAnalysis(array $analysis): array
    {
        $summary = ['create' => 0, 'link' => 0, 'update' => 0, 'skip' => 0, 'conflict' => 0, 'review' => 0];
        foreach ($analysis as $item) {
            $action = $item['action'] ?? 'review';
            if (isset($summary[$action])) {
                $summary[$action]++;
            } else {
                $summary['review']++;
            }
        }
        return $summary;
    }
}
