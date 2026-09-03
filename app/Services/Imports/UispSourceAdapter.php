<?php

namespace App\Services\Imports;

use App\Contracts\ImportSourceInterface;
use App\Dto\Imports\NormalizedRecord;
use App\Integrations\Providers\Uisp\UispClient;
use App\Models\Integration;

class UispSourceAdapter implements ImportSourceInterface
{
    protected UispClient $client;

    public function __construct(Integration $integration)
    {
        $this->client = new UispClient($integration);
    }

    public function getIdentity(): string
    {
        return 'uisp';
    }

    public function getDisplayName(): string
    {
        return 'Ubiquiti UISP';
    }

    public function getCapabilities(): array
    {
        return ['devices', 'sites'];
    }

    public function fetchDevices(): array
    {
        return $this->client->getDevices();
    }

    public function fetchSites(): array
    {
        return $this->client->getSites();
    }

    public function normalizeDevice(array $raw): NormalizedRecord
    {
        $id = $raw['id'] ?? $raw['identification']['id'] ?? null;
        if (!$id) throw new \InvalidArgumentException('UISP device missing ID');

        $ident = $raw['identification'] ?? [];
        
        // Robust IP extraction
        $rawIp = $raw['ipAddress'] 
               ?? $raw['overview']['ipAddress'] 
               ?? $raw['overview']['ip'] 
               ?? $ident['ip'] 
               ?? null;
        
        $ip = $this->normalizeIp($rawIp);
        $mac = $this->normalizeMac($ident['mac'] ?? null);
        $serial = $this->normalizeSerial($ident['serialNumber'] ?? null);

        return new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'device',
            externalId: (string) $id,
            name: $ident['name'] ?? $ident['displayName'] ?? null,
            description: $ident['name'] ?? null,
            macAddress: $mac,
            ipAddress: $ip,
            serialNumber: $serial,
            manufacturer: $ident['vendor'] ?? $ident['vendorName'] ?? 'Ubiquiti',
            model: $ident['model'] ?? $ident['modelName'] ?? null,
            firmwareVersion: $ident['firmwareVersion'] ?? null,
            status: $raw['status'] ?? $ident['status'] ?? null,
            sourceSiteId: $ident['site']['id'] ?? null,
            sourceSiteName: $ident['site']['name'] ?? null,
            rawSource: $raw
        );
    }

    public function normalizeSite(array $raw): NormalizedRecord
    {
        $id = $raw['id'] ?? null;
        if (!$id) throw new \InvalidArgumentException('UISP site missing ID');

        return new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'site',
            externalId: (string) $id,
            name: $raw['name'] ?? null,
            description: $raw['description'] ?? null,
            ipAddress: null,
            macAddress: null,
            serialNumber: null,
            manufacturer: null,
            model: null,
            status: $raw['status'] ?? null,
            metadata: [
                'address' => $raw['address']['fullAddress'] ?? null,
                'latitude' => $raw['location']['lat'] ?? null,
                'longitude' => $raw['location']['lon'] ?? null,
            ],
            rawSource: $raw
        );
    }

    protected function normalizeIp(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim(preg_replace('/\/\d+$/', '', $value));
        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    protected function normalizeMac(?string $value): ?string
    {
        if (!$value) return null;
        $clean = preg_replace('/[^a-fA-F0-9]/', '', $value);
        if (strlen($clean) !== 12 || $clean === '000000000000') return null;
        return strtolower(implode(':', str_split($clean, 2)));
    }

    protected function normalizeSerial(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);
        if (in_array(strtoupper($value), ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', ''])) return null;
        return $value;
    }
}
