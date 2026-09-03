<?php

namespace App\Services\Imports;

use App\Contracts\ImportSourceInterface;
use App\Dto\Imports\NormalizedRecord;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteExternalReference;
use Illuminate\Support\Facades\DB;

class GenericImportService
{
    protected ImportSourceInterface $source;
    protected ReconciliationEngine $engine;

    public function __construct(ImportSourceInterface $source)
    {
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
                // Skip invalid records
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
        $results = ['created' => 0, 'linked' => 0, 'updated' => 0, 'failed' => 0];

        DB::transaction(function () use ($selectedItems, &$results) {
            foreach ($selectedItems as $item) {
                try {
                    $this->processItem($item, $results);
                } catch (\Exception $e) {
                    $results['failed']++;
                }
            }
        });

        return $results;
    }

    protected function processItem(array $item, array &$results): void
    {
        $recordData = $item['record'];
        $analysis = $item['analysis'];
        
        if ($analysis['decision'] === 'CONFLICT') return;

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
            // LINK or UPDATE
            $asset = Asset::find($destId);
            if ($asset) {
                // Update logic here if needed
                $results['linked']++;
                // Ensure external ref exists
                AssetExternalReference::updateOrCreate(
                    ['provider' => $data['provider'], 'external_type' => 'device', 'external_id' => $data['external_id']],
                    ['asset_id' => $asset->id]
                );
            }
        } else {
            // CREATE
            $asset = Asset::create([
                'site_id' => 1, // Default or resolved from site mapping
                'asset_tag' => 'IMP-' . substr($data['external_id'], 0, 8),
                'serial_number' => $data['serial_number'],
                'category' => 'NETWORK',
                'type' => 'Network Device',
                'manufacturer' => $data['manufacturer'],
                'model' => $data['model'],
                'status' => 'OPERATIONAL',
                'description' => $data['name'],
                'specifications' => [
                    'mac_address' => $data['mac_address'],
                    'ip_address' => $data['ip_address'],
                    'firmware_version' => $data['firmware_version'],
                ]
            ]);
            
            AssetExternalReference::create([
                'asset_id' => $asset->id,
                'provider' => $data['provider'],
                'external_type' => 'device',
                'external_id' => $data['external_id'],
            ]);
            $results['created']++;
        }
    }

    protected function processSite(array $data, array $analysis, array &$results): void
    {
        $destId = $analysis['destination_id'];
        
        if ($destId) {
            $results['linked']++;
            SiteExternalReference::updateOrCreate(
                ['provider' => $data['provider'], 'external_type' => 'site', 'external_id' => $data['external_id']],
                ['site_id' => $destId]
            );
        } else {
            $site = Site::create([
                'site_code' => 'IMP-' . substr($data['external_id'], 0, 8),
                'name' => $data['name'] ?? 'Unknown Site',
                'type' => 'pop',
                'status' => 'active',
                'address' => $data['metadata']['address'] ?? null,
                'latitude' => $data['metadata']['latitude'] ?? null,
                'longitude' => $data['metadata']['longitude'] ?? null,
            ]);
            
            SiteExternalReference::create([
                'site_id' => $site->id,
                'provider' => $data['provider'],
                'external_type' => 'site',
                'external_id' => $data['external_id'],
            ]);
            $results['created']++;
        }
    }
}
