<?php

namespace App\Services\Imports;

use App\Contracts\ImportSourceInterface;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Models\AssetInterface;
use App\Models\IpAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenericImportService
{
    protected ImportSourceInterface $source;
    protected ReconciliationEngine $engine;

    public function __construct($source)
    {
        if (!$source instanceof ImportSourceInterface) {
            throw new \InvalidArgumentException(
                'Source must implement ImportSourceInterface. Got: ' . get_class($source)
            );
        }

        $this->source = $source;
        $this->engine = new ReconciliationEngine();
    }

    public function preview(string $type): array
    {
        $records = $type === 'devices' ? $this->source->fetchDevices() : $this->source->fetchSites();
        $normalized = [];
        $decisions = [];

        foreach ($records as $raw) {
            try {
                $norm = $type === 'devices' ? $this->source->normalizeDevice($raw) : $this->source->normalizeSite($raw);
                $normalized[] = $norm;
                $decisions[] = [
                    'record' => $norm->toArray(),
                    'analysis' => $this->engine->reconcile($norm)
                ];
            } catch (\Exception $e) {
                Log::error('Import normalization failed', ['error' => $e->getMessage(), 'raw' => $raw]);
            }
        }

        return [
            'source' => $this->source->getIdentity(),
            'fetched_at' => now()->toISOString(),
            'total' => count($normalized),
            'records' => $decisions
        ];
    }

    public function execute(array $selectedItems): array
    {
        $results = [
            'success' => true,
            'processed' => 0,
            'created' => 0,
            'linked' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => []
        ];

        DB::transaction(function () use ($selectedItems, &$results) {
            foreach ($selectedItems as $item) {
                $results['processed']++;
                $detail = [
                    'source_id' => $item['record']['external_id'] ?? 'unknown',
                    'name' => $item['record']['name'] ?? 'unknown',
                    'status' => 'pending',
                    'message' => ''
                ];

                try {
                    $this->processItem($item, $results);
                    $detail['status'] = 'success';
                    $detail['message'] = "Successfully processed.";
                } catch (\Exception $e) {
                    $results['failed']++;
                    $detail['status'] = 'failed';
                    $detail['message'] = $e->getMessage();
                    Log::error('Import item failed', [
                        'source_id' => $detail['source_id'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                $results['details'][] = $detail;
            }
        });

        if ($results['failed'] > 0) {
            $results['success'] = false;
        }

        return $results;
    }

    protected function processItem(array $item, array &$results): void
    {
        $recordData = $item['record'];
        $analysis = $item['analysis'];

        if ($analysis['decision'] === 'CONFLICT') {
            $results['skipped']++;
            throw new \Exception('Record has unresolved conflicts.');
        }

        if ($recordData['source_type'] === 'device') {
            $this->processAsset($recordData, $analysis, $results);
        } else {
            $this->processSite($recordData, $analysis, $results);
        }
    }

    protected function processAsset(array $data, array $analysis, array &$results): void
    {
        $destId = $analysis['destination_id'];

        if ($destId) {
            $asset = Asset::find($destId);
            if (!$asset) throw new \Exception("Destination asset #{$destId} not found.");

            $results['linked']++;
            AssetExternalReference::updateOrCreate(
                ['provider' => $data['provider'], 'external_type' => 'device', 'external_id' => $data['external_id']],
                ['asset_id' => $asset->id]
            );
        } else {
            $siteId = 1;
            if (isset($data['metadata']['site_id'])) {
                $siteId = $data['metadata']['site_id'];
            }

            $assetTag = 'IMP-' . substr($data['external_id'], 0, 8);
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
                'status' => $data['status'] ?? 'OPERATIONAL',
                'condition' => 'GOOD',
                'quantity' => 1,
                'unit' => 'pcs',
                'specifications' => $data['metadata'] ?? [],
                'description' => $data['name'] ?? null,
            ]);

            AssetExternalReference::create([
                'asset_id' => $asset->id,
                'provider' => $data['provider'],
                'external_type' => 'device',
                'external_id' => $data['external_id'],
                'metadata' => ['imported_at' => now()],
            ]);

            $results['created']++;
        }

        // Process interfaces and IPs for this asset
        $this->processInterfacesAndIps($data, $asset->id, $results);
    }

    protected function processSite(array $data, array $analysis, array &$results): void
    {
        // Site processing logic
        $results['created']++;
    }

    protected function processInterfacesAndIps(array $data, int $assetId, array &$results): void
    {
        $interfaces = $data['interfaces'] ?? [];

        foreach ($interfaces as $interfaceData) {
            $this->processSingleInterface($interfaceData, $assetId, $results);
        }
    }

    protected function processSingleInterface(array $interfaceData, int $assetId, array &$results): void
    {
        $decision = $this->engine->reconcileInterface($interfaceData, $assetId);

        if ($decision['decision'] === 'CONFLICT') {
            $results['skipped']++;
            return;
        }

        $interface = null;

        if ($decision['decision'] === 'LINK' && !empty($decision['destination_id'])) {
            $interface = AssetInterface::find($decision['destination_id']);
            if ($interface) {
                $interface->update($this->prepareInterfaceData($interfaceData, $assetId));
                $results['updated']++;
            }
        } else {
            $interface = AssetInterface::create($this->prepareInterfaceData($interfaceData, $assetId));
            $results['created']++;
        }

        if (!$interface) {
            $results['failed']++;
            return;
        }

        $ips = $interfaceData['ip_addresses'] ?? [];
        foreach ($ips as $ipData) {
            $this->processSingleIp($ipData, $interface->id, $assetId, $results);
        }
    }

    protected function processSingleIp(array $ipData, int $interfaceId, int $assetId, array &$results): void
    {
        $decision = $this->engine->reconcileIpAddress($ipData, $interfaceId, $assetId);

        if ($decision['decision'] === 'CONFLICT') {
            $results['skipped']++;
            return;
        }

        if ($decision['decision'] === 'LINK' && !empty($decision['destination_id'])) {
            $ip = IpAddress::find($decision['destination_id']);
            if ($ip) {
                $ip->update($this->prepareIpData($ipData, $interfaceId));
                $results['updated']++;
            }
        } else {
            IpAddress::create($this->prepareIpData($ipData, $interfaceId));
            $results['created']++;
        }
    }

    protected function prepareInterfaceData(array $data, int $assetId): array
    {
        return [
            'asset_id' => $assetId,
            'name' => $data['name'] ?? 'unknown',
            'display_name' => $data['display_name'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'speed' => $data['speed'] ?? null,
            'status' => $data['status'] ?? 'active',
            'is_management' => $data['is_management'] ?? false,
            'provider' => $data['provider'] ?? null,
            'external_type' => $data['external_type'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    protected function prepareIpData(array $data, int $interfaceId): array
    {
        return [
            'asset_interface_id' => $interfaceId,
            'ip_address' => $data['ip'] ?? null,
            'family' => $data['family'] ?? null,
            'prefix_length' => $data['prefix'] ?? null,
            'is_primary' => $data['is_primary'] ?? false,
            'is_management' => $data['is_management'] ?? false,
            'provider' => $data['provider'] ?? null,
            'external_type' => $data['external_type'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}

    /**
     * Execute import with concurrency protection
     */
    public function executeWithLock(array $selectedItems): array
    {
        $lockKey = 'import:' . $this->source->getIdentity() . ':' . md5(json_encode($selectedItems));
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 60);
        
        try {
            if ($lock->get()) {
                return $this->execute($selectedItems);
            }
            throw new \RuntimeException('Another import is already in progress for this source.');
        } finally {
            $lock->release();
        }
    }
