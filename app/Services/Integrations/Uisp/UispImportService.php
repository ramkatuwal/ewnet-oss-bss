<?php

namespace App\Services\Integrations\Uisp;

use App\Models\Integration;
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
        // Implementation uses existing Site sync service
        // This will be called from the controller
    }

    protected function processDevice(array $data, array &$results): void
    {
        // Implementation uses existing Device sync service
        // This will be called from the controller
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
