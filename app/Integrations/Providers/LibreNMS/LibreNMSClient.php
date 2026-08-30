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

            if ($response->serverError()) {
                throw new \RuntimeException('LibreNMS server error (HTTP ' . $response->status() . ')');
            }

            if ($response->failed()) {
                throw new \RuntimeException('LibreNMS request failed (HTTP ' . $response->status() . ')');
            }

            return $response->json() ?? [];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("LibreNMS connection error: {$e->getMessage()}", ['endpoint' => $endpoint]);
            throw new \RuntimeException('LibreNMS connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Ping the LibreNMS API to verify connectivity.
     */
    public function ping(): array
    {
        $start = microtime(true);
        $result = $this->get('ping');
        $elapsed = round((microtime(true) - $start) * 1000);

        return [
            'success' => ($result['message'] ?? '') === 'pong',
            'response_time_ms' => $elapsed,
        ];
    }

    /**
     * Get system information from LibreNMS.
     */
    public function getSystemInfo(): array
    {
        return $this->get('system');
    }

    /**
     * List all devices with optional filters.
     */
    public function listDevices(array $filters = []): array
    {
        $query = [];
        if (!empty($filters['type'])) {
            $query['type'] = $filters['type'];
        }
        if (!empty($filters['order'])) {
            $query['order'] = $filters['order'];
        }

        $result = $this->get('devices', $query);
        return $result['devices'] ?? [];
    }

    /**
     * Get ports for a specific device.
     */
    public function getDevicePorts(string $deviceIdOrHostname): array
    {
        $result = $this->get("devices/{$deviceIdOrHostname}/ports");
        return $result['ports'] ?? [];
    }

    /**
     * List alerts with optional filters.
     */
    public function listAlerts(array $filters = []): array
    {
        $query = [];
        if (isset($filters['state'])) {
            $query['state'] = $filters['state'];
        }
        if (!empty($filters['severity'])) {
            $query['severity'] = $filters['severity'];
        }

        $result = $this->get('alerts', $query);
        return $result['alerts'] ?? [];
    }

    /**
     * Get pollers list.
     */
    public function getPollers(): array
    {
        $result = $this->get('pollers');
        return $result['pollers'] ?? [];
    }
}
