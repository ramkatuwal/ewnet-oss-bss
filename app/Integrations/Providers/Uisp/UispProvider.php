<?php

namespace App\Integrations\Providers\Uisp;

use App\Contracts\IntegrationProviderInterface;
use App\Models\Integration;
use App\Services\Integrations\Uisp\UispDeviceSyncService;
use App\Services\Integrations\Uisp\UispSiteSyncService;
use Illuminate\Support\Facades\Log;

class UispProvider implements IntegrationProviderInterface
{
    public function identity(): string
    {
        return 'uisp';
    }

    public function displayName(): string
    {
        return 'Ubiquiti UISP';
    }

    public function capabilities(): array
    {
        return [
            'sites',
            'devices',
            'topology',
            'monitoring',
        ];
    }

    public function validateConfiguration(array $config): array
    {
        $errors = [];

        if (empty($config['api_url'])) {
            $errors[] = 'The api_url field is required.';
        } elseif (!filter_var($config['api_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'The api_url must be a valid URL.';
        } elseif (!str_starts_with(strtolower($config['api_url']), 'https://') && !app()->environment('local', 'testing')) {
            $errors[] = 'The api_url must use HTTPS in production environments.';
        }

        if (!isset($config['tls_verify'])) {
            $config['tls_verify'] = true;
        }

        return $errors;
    }

    public function testConnection(Integration $integration): array
    {
        try {
            $start = microtime(true);
            $client = new UispClient($integration);
            $client->getHeartbeat();
            $duration = round((microtime(true) - $start) * 1000);

            return [
                'success' => true,
                'response_time_ms' => $duration,
                'message' => 'Successfully connected to UISP.',
            ];
        } catch (\Throwable $e) {
            Log::error('UISP connection test failed', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id,
            ]);

            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    public function healthCheck(Integration $integration): array
    {
        try {
            $client = new UispClient($integration);
            $start = microtime(true);
            $result = $client->getHeartbeat();
            $duration = round((microtime(true) - $start) * 1000);

            return [
                'status' => 'connected',
                'response_time_ms' => $duration,
                'message' => 'UISP health check passed.',
            ];
        } catch (\Throwable $e) {
            Log::error('UISP health check failed', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id,
            ]);

            return [
                'status' => 'error',
                'error' => 'Health check failed: ' . $e->getMessage(),
            ];
        }
    }

    public function synchronize(Integration $integration): array
    {
        Log::info('UISP Provider synchronization started', [
            'integration_id' => $integration->id,
        ]);

        try {
            $siteSync = new UispSiteSyncService($integration);
            $siteCounts = $siteSync->execute();

            $deviceSync = new UispDeviceSyncService($integration);
            $deviceCounts = $deviceSync->execute();

            $combined = [
                'status' => 'completed',
                'processed' => ($siteCounts['processed'] ?? 0) + ($deviceCounts['processed'] ?? 0),
                'created' => ($siteCounts['created'] ?? 0) + ($deviceCounts['created'] ?? 0),
                'updated' => ($siteCounts['updated'] ?? 0) + ($deviceCounts['updated'] ?? 0),
                'unchanged' => ($siteCounts['unchanged'] ?? 0) + ($deviceCounts['unchanged'] ?? 0),
                'skipped' => ($siteCounts['skipped'] ?? 0) + ($deviceCounts['skipped'] ?? 0),
                'failed' => ($siteCounts['failed'] ?? 0) + ($deviceCounts['failed'] ?? 0),
            ];

            Log::info('UISP Provider synchronization completed', $combined);
            return $combined;
        } catch (\Throwable $e) {
            Log::error('UISP Provider synchronization failed', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id,
            ]);
            throw $e;
        }
    }
}
