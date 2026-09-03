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

        $ip = $this->extractIp($raw);
        $name = $raw['name'] ?? $raw['identification']['name'] ?? 'Unknown Device';
        $mac = $raw['mac'] ?? $raw['identification']['mac'] ?? null;
        $serial = $raw['serialNumber'] ?? $raw['identification']['serialNumber'] ?? null;
        $model = $raw['model'] ?? $raw['identification']['model'] ?? null;
        $manufacturer = $raw['manufacturer'] ?? $raw['identification']['vendor'] ?? null;
        
        $siteName = null;
        $siteId = $raw['siteId'] ?? null;
        if (isset($raw['site']) && is_array($raw['site'])) {
            $siteName = $raw['site']['name'] ?? null;
            $siteId = $siteId ?? ($raw['site']['id'] ?? null);
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
            status: $raw['status'] ?? $raw['identification']['status'] ?? 'unknown',
            metadata: [
                'site_id' => $siteId,
                'site_name' => $siteName,
                'firmware_version' => $raw['firmwareVersion'] ?? null,
            ],
            rawSource: $raw
        );
    }

    public function normalizeSite(array $raw): NormalizedRecord
    {
        $id = $raw['id'] ?? $raw['identification']['id'] ?? null;
        if (!$id) throw new \InvalidArgumentException('UISP site missing ID');

        // Correct mapping for UISP v2.1 structure
        $identification = is_array($raw['identification'] ?? null) ? $raw['identification'] : [];
        $description = is_array($raw['description'] ?? null) ? $raw['description'] : [];
        $location = is_array($description['location'] ?? null) ? $description['location'] : [];

        $name = $identification['name'] ?? 'Unknown Site';
        $status = $identification['status'] ?? 'active';
        
        // Handle note/description text
        $note = $description['note'] ?? null;
        if (is_array($note)) {
            $note = json_encode($note);
        }

        // Extract first IP if available in description
        $ipList = $description['ipAddresses'] ?? [];
        $primaryIp = is_array($ipList) && count($ipList) > 0 ? $ipList[0] : null;

        return new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'site',
            externalId: (string) $id,
            name: $name,
            description: $note,
            ipAddress: $this->normalizeIp($primaryIp),
            macAddress: null,
            serialNumber: null,
            manufacturer: null,
            model: null,
            status: $status,
            metadata: [
                'address' => null, // UISP sites don't always have a structured street address
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'parent_id' => $identification['parent']['id'] ?? null,
                'parent_name' => $identification['parent']['name'] ?? null,
                'device_count' => $description['deviceCount'] ?? 0,
            ],
            rawSource: $raw
        );
    }

    protected function extractIp(array $raw): ?string
    {
        if (!empty($raw['ip'])) return $raw['ip'];
        if (!empty($raw['managementIp'])) return $raw['managementIp'];
        if (!empty($raw['ipAddress'])) return $raw['ipAddress'];
        
        if (!empty($raw['ipAddressList']) && is_array($raw['ipAddressList'])) {
            foreach ($raw['ipAddressList'] as $entry) {
                if (isset($entry['address'])) return $entry['address'];
                if (isset($entry['ip'])) return $entry['ip'];
            }
        }

        if (!empty($raw['interfaces']) && is_array($raw['interfaces'])) {
            foreach ($raw['interfaces'] as $iface) {
                if (!is_array($iface)) continue;
                if (!empty($iface['ip'])) return $iface['ip'];
                
                if (!empty($iface['addresses']) && is_array($iface['addresses'])) {
                    foreach ($iface['addresses'] as $addr) {
                        if (isset($addr['address'])) {
                            return preg_replace('/\/\d+$/', '', $addr['address']);
                        }
                    }
                }
            }
        }

        return null;
    }

    protected function normalizeIp(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim(preg_replace('/\/\d+$/', '', $value));
        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }
}
