<?php

namespace App\Integrations\Providers\Uisp;

use App\Contracts\IntegrationProviderInterface;
use App\Models\Integration;
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

        return $errors;
    }

    public function testConnection(Integration $integration): array
    {
        try {
            $start = microtime(true);
            $client = new UispClient($integration);
            $client->request('GET', '/nms/api/v2.1/nms/heartbeat');
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
            $result = $client->request('GET', '/nms/api/v2.1/nms/heartbeat');
            
            $isAlive = isset($result['result']) && $result['result'] === true;

            return [
                'status' => $isAlive ? 'connected' : 'degraded',
                'details' => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'error' => 'Health check failed: ' . $e->getMessage(),
            ];
        }
    }

    public function synchronize(Integration $integration, string $operation = 'full'): array
    {
        try {
            $syncService = new UispSiteSyncService($integration);
            $counts = $syncService->execute();

            return [
                'status' => 'completed',
                'counts' => $counts,
                'message' => 'Site synchronization completed successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('UISP synchronization failed', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id,
            ]);

            return [
                'status' => 'failed',
                'error' => 'Synchronization failed: ' . $e->getMessage(),
            ];
        }
    }
}
