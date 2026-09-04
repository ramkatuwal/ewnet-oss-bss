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

        $record = new NormalizedRecord();
        $record->sourceType = 'device';
        $record->provider = 'uisp';
        $record->externalId = (string) $id;
        $record->name = $name;
        $record->description = $raw['description'] ?? null;
        $record->serialNumber = $raw['serial'] ?? null;
        $record->macAddress = $raw['mac'] ?? null;
        $record->ipAddress = $ip;
        $record->model = $raw['model'] ?? $raw['type'] ?? null;
        $record->manufacturer = $raw['vendor'] ?? null;
        $record->status = $raw['status'] ?? null;
        $record->metadata = $raw;

        // Extract interfaces from UISP device
        $record->interfaces = $this->extractInterfaces($raw);
        $record->ipAddresses = $this->extractIpAddresses($raw);

        return $record;
    }

    public function normalizeSite(array $raw): NormalizedRecord
    {
        $id = $raw['id'] ?? $raw['uuid'] ?? null;
        if (!$id) throw new \InvalidArgumentException('UISP site missing ID');

        $record = new NormalizedRecord();
        $record->sourceType = 'site';
        $record->provider = 'uisp';
        $record->externalId = (string) $id;
        $record->name = $raw['name'] ?? 'Unknown Site';
        $record->description = $raw['description'] ?? null;
        $record->metadata = $raw;

        return $record;
    }

    protected function extractIp(array $raw): ?string
    {
        return $raw['ip'] ?? $raw['management_ip'] ?? $raw['address'] ?? null;
    }

    /**
     * Extract interfaces from UISP device data
     */
    protected function extractInterfaces(array $device): array
    {
        $interfaces = [];
        $deviceId = $device['id'] ?? $device['uuid'] ?? null;

        foreach ($device['interfaces'] ?? [] as $interface) {
            $interfaces[] = [
                'name' => $interface['name'] ?? 'unknown',
                'display_name' => $interface['name'] ?? null,
                'description' => $interface['description'] ?? null,
                'type' => $interface['type'] ?? null,
                'mac_address' => $interface['mac'] ?? null,
                'speed' => isset($interface['speed']) ? (int) $interface['speed'] : null,
                'status' => $interface['status'] ?? null,
                'provider' => 'uisp',
                'external_type' => 'interface',
                'external_id' => (string) ($interface['id'] ?? $interface['uuid'] ?? null),
                'metadata' => $interface,
                'ip_addresses' => $this->extractIpAddressesFromInterface($interface),
            ];
        }

        // Also add management IP as an interface if present
        if (!empty($device['management_ip'])) {
            $interfaces[] = [
                'name' => 'management',
                'display_name' => 'Management IP',
                'description' => 'Management interface',
                'type' => 'management',
                'provider' => 'uisp',
                'external_type' => 'management_ip',
                'external_id' => $deviceId . '_mgmt',
                'metadata' => ['management_ip' => $device['management_ip']],
                'ip_addresses' => [
                    [
                        'ip' => $device['management_ip'],
                        'prefix' => 32,
                        'is_primary' => true,
                        'is_management' => true,
                        'provider' => 'uisp',
                        'external_type' => 'management_ip',
                        'external_id' => $deviceId . '_mgmt_ip',
                    ]
                ],
            ];
        }

        return $interfaces;
    }

    /**
     * Extract IP addresses from UISP interface
     */
    protected function extractIpAddressesFromInterface(array $interface): array
    {
        $ips = [];

        foreach ($interface['ip_addresses'] ?? [] as $ipData) {
            $ip = $ipData['address'] ?? $ipData['ip'] ?? null;
            if (!$ip) continue;

            $ips[] = [
                'ip' => $ip,
                'prefix' => $ipData['prefix'] ?? $ipData['mask'] ?? null,
                'is_primary' => $ipData['primary'] ?? false,
                'is_management' => $ipData['management'] ?? false,
                'provider' => 'uisp',
                'external_type' => 'interface_ip',
                'external_id' => $ipData['id'] ?? null,
                'metadata' => $ipData,
            ];
        }

        return $ips;
    }

    /**
     * Extract IP addresses from UISP device
     */
    protected function extractIpAddresses(array $device): array
    {
        $ips = [];

        // Device-level IPs
        if (!empty($device['ip'])) {
            $ips[] = [
                'ip' => $device['ip'],
                'prefix' => null,
                'is_primary' => true,
                'is_management' => true,
                'provider' => 'uisp',
                'external_type' => 'device_ip',
                'external_id' => ($device['id'] ?? $device['uuid'] ?? '') . '_ip',
                'metadata' => ['source' => 'device'],
            ];
        }

        return $ips;
    }
}
