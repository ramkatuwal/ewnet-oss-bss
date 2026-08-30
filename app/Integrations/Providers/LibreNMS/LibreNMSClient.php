<?php

namespace App\Integrations\Providers\LibreNMS;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LibreNMSClient
{
    private string $baseUrl;
    private string $apiToken;
    private int $timeout;
    private bool $tlsVerify;

    public function __construct(Integration $integration)
    {
        $config = $integration->configuration ?? [];
        $this->baseUrl = rtrim($config['endpoint'] ?? '', '/');
        $this->timeout = $config['timeout'] ?? 30;
        $this->tlsVerify = $config['tls_verify'] ?? true;

        $credential = $integration->activeCredentials()
            ->where('credential_type', 'api_token')
            ->first();

        $this->apiToken = $credential ? $credential->getSecretValue() : '';
    }

    /**
     * Make an authenticated GET request to LibreNMS API.
     * Returns ['data' => array, 'status' => int] on success.
     * Throws on auth/network errors.
     * Returns ['data' => null, 'status' => 404] for 404 responses.
     */
    public function get(string $endpoint, array $query = []): array
    {
        $url = $this->baseUrl . '/api/v0/' . ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiToken,
            ])
                ->timeout($this->timeout)
                ->when(!$this->tlsVerify, fn ($r) => $r->withoutVerifying())
                ->get($url, $query);

            if ($response->status() === 401 || $response->status() === 403) {
                throw new \RuntimeException('LibreNMS authentication failed (HTTP ' . $response->status() . ')');
            }

            if ($response->status() === 429) {
                throw new \RuntimeException('LibreNMS rate limit exceeded');
            }

            if ($response->status() === 404) {
                return ['data' => null, 'status' => 404];
            }

            if ($response->serverError()) {
                throw new \RuntimeException('LibreNMS server error (HTTP ' . $response->status() . ')');
            }

            if ($response->failed()) {
                throw new \RuntimeException('LibreNMS request failed (HTTP ' . $response->status() . ')');
            }

            return ['data' => $response->json() ?? [], 'status' => 200];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("LibreNMS connection error: {$e->getMessage()}", ['endpoint' => $endpoint]);
            throw new \RuntimeException('LibreNMS connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Legacy get method for backward compatibility.
     * Returns just the data array, throws on 404.
     */
    public function getOrThrow(string $endpoint, array $query = []): array
    {
        $result = $this->get($endpoint, $query);
        if ($result['status'] === 404) {
            throw new \RuntimeException('LibreNMS resource not found (HTTP 404)');
        }
        return $result['data'];
    }

    public function ping(): array
    {
        $start = microtime(true);
        $result = $this->getOrThrow('ping');
        $elapsed = round((microtime(true) - $start) * 1000);

        return [
            'success' => ($result['message'] ?? '') === 'pong',
            'response_time_ms' => $elapsed,
        ];
    }

    public function getSystemInfo(): array
    {
        return $this->getOrThrow('system');
    }

    public function listDevices(array $filters = []): array
    {
        $query = [];
        if (!empty($filters['type'])) {
            $query['type'] = $filters['type'];
        }
        if (!empty($filters['order'])) {
            $query['order'] = $filters['order'];
        }

        $result = $this->getOrThrow('devices', $query);
        return $result['devices'] ?? [];
    }

    /**
     * Get ports for a specific device with full column data.
     * Returns ['ports' => array, 'status' => int].
     * status=404 means device has no port data (stale/unpolled).
     */
    public function getDevicePorts(string $deviceIdOrHostname): array
    {
        $columns = 'port_id,device_id,ifIndex,ifName,ifDescr,ifAlias,ifType,ifSpeed,ifAdminStatus,ifOperStatus';
        $result = $this->get("devices/{$deviceIdOrHostname}/ports", ['columns' => $columns]);

        if ($result['status'] === 404) {
            return ['ports' => [], 'status' => 404];
        }

        return ['ports' => $result['data']['ports'] ?? [], 'status' => 200];
    }

    public function listAlerts(array $filters = []): array
    {
        $query = [];
        if (isset($filters['state'])) {
            $query['state'] = $filters['state'];
        }
        if (!empty($filters['severity'])) {
            $query['severity'] = $filters['severity'];
        }

        $result = $this->getOrThrow('alerts', $query);
        return $result['alerts'] ?? [];
    }

    public function getPollers(): array
    {
        $result = $this->getOrThrow('pollers');
        return $result['pollers'] ?? [];
    }
}
