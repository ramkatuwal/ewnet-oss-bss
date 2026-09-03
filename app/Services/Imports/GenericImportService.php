<?php

namespace App\Services\Imports;

use App\Contracts\ImportSourceInterface;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Site;
use App\Models\SiteExternalReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenericImportService
{
    protected ImportSourceInterface $source;
    protected ReconciliationEngine $engine;

    /**
     * @param mixed $source
     */
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
            // LINK or UPDATE
            $asset = Asset::find($destId);
            if (!$asset) throw new \Exception("Destination asset #{$destId} not found.");
            
            $results['linked']++;
            // Ensure external ref exists
            AssetExternalReference::updateOrCreate(
                ['provider' => $data['provider'], 'external_type' => 'device', 'external_id' => $data['external_id']],
                ['asset_id' => $asset->id]
            );
        } else {
            // CREATE
            $siteId = 1; // Default site or logic to resolve site from metadata
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
            $siteCode = 'IMP-' . substr($data['external_id'], 0, 8);
            $baseCode = $siteCode;
            $counter = 1;
            while (Site::where('site_code', $siteCode)->exists()) {
                $siteCode = $baseCode . '-' . $counter++;
            }

            $site = Site::create([
                'site_code' => $siteCode,
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
