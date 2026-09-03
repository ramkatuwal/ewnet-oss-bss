<?php

namespace App\Services\Imports;

use App\Contracts\ImportSourceInterface;
use App\Dto\Imports\NormalizedRecord;
use App\Integrations\Providers\Uisp\UispClient;
use App\Models\Integration;
use Illuminate\Support\Arr;

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
        return 'UISP / UNMS';
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

        $name = $raw['name'] ?? $raw['identification']['name'] ?? 'Unknown Device';
        $mac = $raw['mac'] ?? $raw['identification']['mac'] ?? null;
        $ip = $raw['ip'] ?? $raw['managementIp'] ?? null;
        $serial = $raw['serialNumber'] ?? $raw['identification']['serialNumber'] ?? null;
        $model = $raw['model'] ?? $raw['identification']['model'] ?? null;
        $manufacturer = $raw['manufacturer'] ?? $raw['identification']['vendor'] ?? null;
        
        // Resolve Site Name from raw data if available
        $siteName = null;
        if (isset($raw['site']) && is_array($raw['site'])) {
            $siteName = $raw['site']['name'] ?? null;
        } elseif (isset($raw['siteId'])) {
            // In some UISP versions, siteId is just an ID. We might need to map it later.
            // For now, we'll store the ID in metadata.
        }

        return new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'device',
            externalId: (string) $id,
            name: $name,
            description: $name,
            ipAddress: $this->normalizeIp($ip),
            macAddress: $mac ? strtolower($mac) : null,
            serialNumber: $serial,
            manufacturer: $manufacturer,
            model: $model,
            status: $raw['status'] ?? 'unknown',
            metadata: [
                'site_id' => $raw['siteId'] ?? null,
                'site_name' => $siteName,
                'firmware_version' => $raw['firmwareVersion'] ?? null,
            ],
            rawSource: $raw
        );
    }

    public function normalizeSite(array $raw): NormalizedRecord
    {
        $id = $raw['id'] ?? null;
        if (!$id) throw new \InvalidArgumentException('UISP site missing ID');

        // Safe navigation for nested arrays
        $addressData = $raw['address'] ?? [];
        $locationData = $raw['location'] ?? [];

        return new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'site',
            externalId: (string) $id,
            name: $raw['name'] ?? 'Unknown Site',
            description: $raw['description'] ?? null,
            ipAddress: null,
            macAddress: null,
            serialNumber: null,
            manufacturer: null,
            model: null,
            status: $raw['status'] ?? 'active',
            metadata: [
                'address' => $addressData['fullAddress'] ?? $addressData['street'] ?? null,
                'latitude' => $locationData['lat'] ?? $locationData['latitude'] ?? null,
                'longitude' => $locationData['lon'] ?? $locationData['longitude'] ?? null,
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
}
