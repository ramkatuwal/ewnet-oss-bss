<?php

namespace App\Integrations\Providers\Uisp;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UispClient
{
    protected Integration $integration;
    protected string $baseUrl;
    protected string $token;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
        
        // Canonical configuration key is 'api_url' based on live forensic evidence
        $this->baseUrl = rtrim($integration->configuration['api_url'] ?? '', '/');
        
        if (empty($this->baseUrl)) {
            throw new \InvalidArgumentException('UISP integration missing api_url in configuration.');
        }

        $this->token = $this->resolveToken();
    }

    protected function resolveToken(): string
    {
        $credential = $this->integration->activeCredentials()
            ->where('credential_type', 'api_token')
            ->first();

        if (!$credential) {
            throw new \InvalidArgumentException('UISP integration missing active api_token credential.');
        }

        return $credential->getSecretValue();
    }

    public function request(string $method, string $path, array $options = []): array
    {
        $url = $this->buildUrl($path);
        $this->validateUrl($url);

        try {
            $response = Http::withHeaders([
                'x-auth-token' => $this->token,
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->connectTimeout(10)
            ->withoutVerifying(!$this->integration->configuration['tls_verify'] ?? true)
            ->$method($url, $options);

            if ($response->failed()) {
                Log::error('UISP API request failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);
                throw new \RuntimeException("UISP API returned status {$response->status()}");
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('UISP Client error', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);
            throw $e;
        }
    }

    protected function buildUrl(string $path): string
    {
        // Ensure path starts with /
        $path = '/' . ltrim($path, '/');
        return $this->baseUrl . $path;
    }

    /**
     * SSRF Protection: Validate URL before making request
     */
    protected function validateUrl(string $url): void
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            throw new \InvalidArgumentException('Invalid URL format.');
        }

        $host = $parsed['host'];
        
        // Enforce HTTPS in production
        if (($parsed['scheme'] ?? '') !== 'https' && app()->environment('production')) {
            throw new \InvalidArgumentException('UISP base_url must use HTTPS in production.');
        }

        // Skip DNS/IP checks during unit tests to allow Http::fake() to work
        if (app()->runningUnitTests()) {
            return;
        }

        // Resolve host to IP to check for private ranges
        $ips = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!$ips) {
            throw new \InvalidArgumentException("Unable to resolve host: {$host}");
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip['ip'])) {
                throw new \InvalidArgumentException("Access to private/internal IP addresses is forbidden for security reasons.");
            }
        }
    }

    protected function isPrivateIp(string $ip): bool
    {
        $ipLong = ip2long($ip);
        
        // IPv4 Private Ranges
        if ($ipLong !== false) {
            $privateRanges = [
                ['0.0.0.0', '0.255.255.255'], // Current network
                ['10.0.0.0', '10.255.255.255'], // Class A
                ['127.0.0.0', '127.255.255.255'], // Loopback
                ['169.254.0.0', '169.254.255.255'], // Link-local
                ['172.16.0.0', '172.31.255.255'], // Class B
                ['192.168.0.0', '192.168.255.255'], // Class C
            ];

            foreach ($privateRanges as $range) {
                if ($ipLong >= ip2long($range[0]) && $ipLong <= ip2long($range[1])) {
                    return true;
                }
            }
        }

        // IPv6 Checks
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

    /**
     * Fetch all sites from UISP
     */
    public function getSites(): array
    {
        return $this->request('GET', '/nms/api/v2.1/sites');
    }

    /**
     * Fetch changed objects since timestamp
     */
    public function getChangedSince(string $timestamp): array
    {
        return $this->request('GET', '/nms/api/v2.1/nms/changed', ['since' => $timestamp]);
    }
}
