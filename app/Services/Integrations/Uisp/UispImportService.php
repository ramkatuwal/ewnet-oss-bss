<?php

namespace App\Services\Integrations\Uisp;

use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Integrations\Providers\Uisp\UispClient;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UispImportService
{
    protected Integration $integration;
    protected UispClient $client;
    protected UispDuplicateDetector $detector;
    protected array $results = [
        'sites' => ['created' => 0, 'updated' => 0, 'linked' => 0, 'skipped' => 0, 'failed' => 0, 'conflicts' => 0],
        'devices' => ['created' => 0, 'updated' => 0, 'linked' => 0, 'skipped' => 0, 'failed' => 0, 'conflicts' => 0],
    ];

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        $this->client = new UispClient($integration);
        $this->detector = new UispDuplicateDetector();
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
        ];

        // Process sites first
        if (!empty($selectedRecords['sites'])) {
            foreach ($selectedRecords['sites'] as $siteData) {
                $this->processSite($siteData, $results['sites']);
            }
        }

        // Process devices
        if (!empty($selectedRecords['devices'])) {
            foreach ($selectedRecords['devices'] as $deviceData) {
                $this->processDevice($deviceData, $results['devices']);
            }
        }

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
            $siteSync = new UispSiteSyncService($this->integration);
            // We need to fetch the raw site data again to pass to syncSingleSite
            // Or we can refactor UispSiteSyncService to accept mapped data.
            // For now, let's assume the preview data contains enough info or we re-fetch.
            // To keep it simple and safe, we'll use the existing sync logic which is idempotent.
            
            // Since UispSiteSyncService expects raw UISP data, and we only have analysis data here,
            // we should ideally re-fetch or pass the raw data from the frontend.
            // Given the current architecture, the frontend sends back the 'analysis' object.
            // We will implement a direct write here for the import flow.
            
            $name = $data['name'] ?? 'Unknown Site';
            $address = $data['address'] ?? null;
            $lat = $data['latitude'] ?? null;
            $lon = $data['longitude'] ?? null;

            DB::transaction(function () use ($externalId, $name, $address, $lat, $lon, &$results) {
                $ref = SiteExternalReference::where('provider', 'uisp')
                    ->where('external_type', 'site')
                    ->where('external_id', $externalId)
                    ->first();

                if ($ref && $ref->site) {
                    $results['linked']++;
                    // Update if needed
                    $site = $ref->site;
                    $updates = [];
                    if ($site->name !== $name) $updates['name'] = $name;
                    if ($site->address !== $address) $updates['address'] = $address;
                    if ($site->latitude != $lat) $updates['latitude'] = $lat;
                    if ($site->longitude != $lon) $updates['longitude'] = $lon;
                    
                    if (!empty($updates)) {
                        $site->update($updates);
                        $results['updated']++;
                    }
                } else {
                    $siteCode = 'UISP-' . substr($externalId, 0, 8);
                    $baseCode = $siteCode;
                    $counter = 1;
                    while (Site::where('site_code', $siteCode)->exists()) {
                        $siteCode = $baseCode . '-' . $counter++;
                    }

                    $site = Site::create([
                        'site_code' => $siteCode,
                        'name' => $name,
                        'type' => 'pop',
                        'status' => 'active',
                        'address' => $address,
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'metadata' => ['synced_from' => 'uisp', 'synced_at' => now()->toISOString()],
                    ]);

                    SiteExternalReference::create([
                        'site_id' => $site->id,
                        'provider' => 'uisp',
                        'external_type' => 'site',
                        'external_id' => $externalId,
                    ]);
                    $results['created']++;
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to import UISP site', ['external_id' => $externalId, 'error' => $e->getMessage()]);
            $results['failed']++;
        }
    }

    protected function processDevice(array $data, array &$results): void
    {
        $externalId = $data['external_id'] ?? null;
        if (!$externalId) {
            $results['failed']++;
            return;
        }

        $action = $data['action'] ?? 'error';
        if ($action === 'conflict') {
            $results['conflicts']++;
            return;
        }

        try {
            DB::transaction(function () use ($data, $externalId, &$results) {
                $ref = AssetExternalReference::where('provider', 'uisp')
                    ->where('external_type', 'device')
                    ->where('external_id', $externalId)
                    ->first();

                $assetData = [
                    'model' => $data['model'],
                    'manufacturer' => $data['vendor'],
                    'serial_number' => $data['serial'],
                    'type' => 'Network Device',
                    'category' => 'NETWORK',
                    'status' => 'OPERATIONAL', // Default status for imported devices
                    'specifications' => [
                        'mac_address' => $data['mac'],
                        'ip_address' => $data['ip'],
                        'synced_from' => 'uisp',
                        'synced_at' => now()->toISOString(),
                    ],
                    'description' => $data['name'],
                ];

                if ($ref && $ref->asset) {
                    $results['linked']++;
                    $asset = $ref->asset;
                    $updates = [];
                    if ($asset->model !== $assetData['model']) $updates['model'] = $assetData['model'];
                    if ($asset->manufacturer !== $assetData['manufacturer']) $updates['manufacturer'] = $assetData['manufacturer'];
                    if ($asset->serial_number !== $assetData['serial_number']) $updates['serial_number'] = $assetData['serial_number'];
                    
                    // Merge specifications
                    $existingSpecs = $asset->specifications ?? [];
                    $newSpecs = $assetData['specifications'];
                    $mergedSpecs = array_merge($existingSpecs, $newSpecs);
                    $updates['specifications'] = $mergedSpecs;

                    if (!empty($updates)) {
                        $asset->update($updates);
                        $results['updated']++;
                    }
                } else {
                    // Resolve site if available in data
                    $siteId = null;
                    if (isset($data['site_id'])) {
                        $siteId = $data['site_id'];
                    }

                    $assetTag = 'UISP-' . substr($externalId, 0, 8);
                    $baseTag = $assetTag;
                    $counter = 1;
                    while (Asset::where('asset_tag', $assetTag)->exists()) {
                        $assetTag = $baseTag . '-' . $counter++;
                    }
                    $assetData['asset_tag'] = $assetTag;
                    if ($siteId) $assetData['site_id'] = $siteId;

                    $asset = Asset::create($assetData);
                    AssetExternalReference::create([
                        'asset_id' => $asset->id,
                        'provider' => 'uisp',
                        'external_type' => 'device',
                        'external_id' => $externalId,
                    ]);
                    $results['created']++;
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to import UISP device', ['external_id' => $externalId, 'error' => $e->getMessage()]);
            $results['failed']++;
        }
    }

    protected function summarizeAnalysis(array $analysis): array
    {
        $summary = ['create' => 0, 'link' => 0, 'update' => 0, 'conflict' => 0, 'skip' => 0, 'error' => 0];
        foreach ($analysis as $item) {
            $action = $item['action'] ?? 'error';
            if (isset($summary[$action])) {
                $summary[$action]++;
            } else {
                $summary['error']++;
            }
        }
        return $summary;
    }
}
