<?php

namespace App\Integrations\Providers\LibreNMS;

use App\Contracts\IntegrationProviderInterface;
use App\Models\Integration;
use App\Models\LibreNmsObject;
use App\Services\SiteMappingService;
use Illuminate\Support\Facades\Log;

class LibreNMSProvider implements IntegrationProviderInterface
{
    public function identity(): string
    {
        return 'librenms';
    }

    public function displayName(): string
    {
        return 'LibreNMS';
    }

    public function capabilities(): array
    {
        return [
            'device_discovery',
            'interface_discovery',
            'monitoring',
            'alerts',
            'site_mapping',
        ];
    }

    public function validateConfiguration(array $config): array
    {
        $errors = [];

        // Support both 'endpoint' (legacy/internal) and 'api_url' (frontend)
        $url = $config['endpoint'] ?? $config['api_url'] ?? null;

        if (empty($url)) {
            $errors[] = 'Endpoint URL is required.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'Endpoint must be a valid URL.';
        }

        if (isset($config['timeout']) && ($config['timeout'] < 1 || $config['timeout'] > 300)) {
            $errors[] = 'Timeout must be between 1 and 300 seconds.';
        }

        return $errors;
    }

    public function testConnection(Integration $integration): array
    {
        $client = new LibreNMSClient($integration);
        $result = $client->ping();

        Log::info('LibreNMS connection test', [
            'integration_id' => $integration->id,
            'success' => $result['success'],
            'response_time_ms' => $result['response_time_ms'],
        ]);

        return $result;
    }

    public function healthCheck(Integration $integration): array
    {
        try {
            $client = new LibreNMSClient($integration);
            $start = microtime(true);
            $systemInfo = $client->getSystemInfo();
            $elapsed = round((microtime(true) - $start) * 1000);

            $version = $systemInfo['system'][0]['local_ver'] ?? 'unknown';

            return [
                'status' => 'connected',
                'response_time_ms' => $elapsed,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            Log::warning("LibreNMS health check failed: {$e->getMessage()}", [
                'integration_id' => $integration->id,
            ]);

            $msg = $e->getMessage();
            if (str_contains($msg, 'rate limit') || str_contains($msg, '502') || str_contains($msg, '503')) {
                return ['status' => 'degraded', 'error' => 'Temporary server issue'];
            }

            return ['status' => 'failed', 'error' => 'Health check failed'];
        }
    }

    public function synchronize(Integration $integration, string $operation = 'full'): array
    {
        $client = new LibreNMSClient($integration);
        $counts = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'sites_mapped' => 0,
            'sites_unmapped' => 0,
        ];

        // Fetch devices once to use for both device sync and site mapping
        $devices = [];
        try {
            $devices = $client->listDevices(['type' => 'all']);
        } catch (\Throwable $e) {
            Log::error("LibreNMS device fetch failed: {$e->getMessage()}", [
                'integration_id' => $integration->id,
            ]);
            $counts['failed']++;
            return $counts;
        }

        // Sync order: devices → ports → alerts → pollers → sites
        $this->syncDevices($devices, $integration, $counts);
        $this->syncPorts($client, $integration, $counts);
        $this->syncAlerts($client, $integration, $counts);
        $this->syncPollers($client, $integration, $counts);

        // New: Site Mapping using the already-fetched device list
        $this->syncSites($devices, $integration, $counts);

        Log::info('LibreNMS synchronization completed', [
            'integration_id' => $integration->id,
            'operation' => $operation,
            'counts' => $counts,
        ]);

        return $counts;
    }

    private function syncDevices(array $devices, Integration $integration, array &$counts): void
    {
        foreach ($devices as $device) {
            $externalId = (string) ($device['device_id'] ?? '');
            if (empty($externalId)) {
                $counts['failed']++;
                continue;
            }

            $result = LibreNmsObject::upsertObject(
                $integration->id,
                'device',
                $externalId,
                $device,
                $device['hostname'] ?? $device['sysName'] ?? null,
                $device['status'] ?? null,
            );

            $counts['processed']++;
            match ($result) {
                'created' => $counts['created']++,
                'updated' => $counts['updated']++,
                'unchanged' => $counts['unchanged']++,
            };
        }
    }

    private function syncPorts(LibreNMSClient $client, Integration $integration, array &$counts): void
    {
        $devices = LibreNmsObject::where('integration_id', $integration->id)
            ->where('object_type', 'device')
            ->get();

        foreach ($devices as $deviceObj) {
            try {
                $portResult = $client->getDevicePorts($deviceObj->external_id);

                if ($portResult['status'] === 404) {
                    $counts['skipped']++;
                    continue;
                }

                $ports = $portResult['ports'];

                foreach ($ports as $port) {
                    $portId = (string) ($port['port_id'] ?? '');
                    if (empty($portId)) {
                        $counts['failed']++;
                        continue;
                    }

                    $result = LibreNmsObject::upsertObject(
                        $integration->id,
                        'port',
                        $portId,
                        $port,
                        $port['ifName'] ?? $port['ifDescr'] ?? null,
                        $port['ifOperStatus'] ?? null,
                        $deviceObj->external_id,
                    );

                    $counts['processed']++;
                    match ($result) {
                        'created' => $counts['created']++,
                        'updated' => $counts['updated']++,
                        'unchanged' => $counts['unchanged']++,
                    };
                }
            } catch (\Throwable $e) {
                $counts['failed']++;
            }
        }
    }

    private function syncAlerts(LibreNMSClient $client, Integration $integration, array &$counts): void
    {
        try {
            $alerts = $client->listAlerts();

            foreach ($alerts as $alert) {
                $alertId = (string) ($alert['alert_id'] ?? $alert['id'] ?? '');
                if (empty($alertId)) {
                    $counts['failed']++;
                    continue;
                }

                $deviceId = isset($alert['device_id']) ? (string) $alert['device_id'] : null;

                $result = LibreNmsObject::upsertObject(
                    $integration->id,
                    'alert',
                    $alertId,
                    $alert,
                    $alert['name'] ?? $alert['rule'] ?? null,
                    $alert['severity'] ?? null,
                    $deviceId,
                );

                $counts['processed']++;
                match ($result) {
                    'created' => $counts['created']++,
                    'updated' => $counts['updated']++,
                    'unchanged' => $counts['unchanged']++,
                };
            }
        } catch (\Throwable $e) {
            $counts['failed']++;
        }
    }

    private function syncPollers(LibreNMSClient $client, Integration $integration, array &$counts): void
    {
        try {
            $pollers = $client->getPollers();

            foreach ($pollers as $poller) {
                $pollerId = (string) ($poller['id'] ?? $poller['poller_name'] ?? '');
                if (empty($pollerId)) {
                    $counts['failed']++;
                    continue;
                }

                $result = LibreNmsObject::upsertObject(
                    $integration->id,
                    'poller',
                    $pollerId,
                    $poller,
                    $poller['poller_name'] ?? null,
                    $poller['status'] ?? null,
                );

                $counts['processed']++;
                match ($result) {
                    'created' => $counts['created']++,
                    'updated' => $counts['updated']++,
                    'unchanged' => $counts['unchanged']++,
                };
            }
        } catch (\Throwable $e) {
            $counts['failed']++;
        }
    }

    private function syncSites(array $devices, Integration $integration, array &$counts): void
    {
        $mappingService = app(SiteMappingService::class);

        foreach ($devices as $device) {
            $result = $mappingService->mapDevice($device, $integration);

            $counts['processed']++;
            if ($result['status'] === 'mapped') {
                $counts['sites_mapped']++;
            } elseif ($result['status'] === 'unmapped') {
                $counts['sites_unmapped']++;
            } else {
                $counts['failed']++;
            }
        }
    }
}
