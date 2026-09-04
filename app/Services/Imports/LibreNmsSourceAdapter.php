<?php

namespace App\Services\Imports;

use App\Contracts\ImportSourceInterface;
use App\Dto\Imports\NormalizedRecord;
use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class LibreNmsSourceAdapter implements ImportSourceInterface
{
    protected LibreNMSClient $client;

    public function __construct(Integration $integration)
    {
        $this->client = new LibreNMSClient($integration);
    }

    public function getIdentity(): string
    {
        return 'librenms';
    }

    public function getDisplayName(): string
    {
        return 'LibreNMS';
    }

    public function getCapabilities(): array
    {
        return ['devices', 'sites'];
    }

    public function fetchDevices(): array
    {
        try {
            return $this->client->listDevices(['type' => 'all']);
        } catch (\Throwable $e) {
            Log::error('LibreNMS fetchDevices failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function fetchSites(): array
    {
        // LibreNMS doesn't have a native "Site" endpoint like UISP.
        // We derive sites from the 'location' or 'sysLocation' of devices.
        $devices = $this->fetchDevices();
        $sites = [];
        $seen = [];

        foreach ($devices as $device) {
            $location = $device['location'] ?? $device['sysLocation'] ?? null;
            if ($location && !in_array($location, $seen)) {
                $seen[] = $location;
                $sites[] = [
                    'name' => $location,
                    'external_id' => 'location-' . md5($location),
                    'devices' => array_filter($devices, fn($d) => ($d['location'] ?? $d['sysLocation'] ?? null) === $location),
                ];
            }
        }

        return $sites;
    }

    public function normalizeDevice(array $raw): NormalizedRecord
    {
        $id = $raw['device_id'] ?? null;
        if (!$id) throw new \InvalidArgumentException('LibreNMS device missing device_id');

        $record = new NormalizedRecord();
        $record->sourceType = 'device';
        $record->provider = 'librenms';
        $record->externalId = (string) $id;
        $record->name = $raw['display'] ?? $raw['sysName'] ?? $raw['hostname'] ?? 'Unknown Device';
        $record->description = $raw['sysDescr'] ?? null;
        $record->serialNumber = $raw['serial'] ?? null;
        $record->macAddress = null; // Not directly available in device endpoint
        $record->ipAddress = $raw['ip'] ?? $raw['hostname'] ?? null;
        $record->model = $raw['hardware'] ?? null;
        $record->manufacturer = $raw['os'] ?? null;
        $record->status = $raw['status'] ?? null;
        $record->metadata = $raw;

        // Extract interfaces if available
        $record->interfaces = $this->extractInterfacesFromDevice($raw);

        return $record;
    }

    public function normalizeSite(array $raw): NormalizedRecord
    {
        $record = new NormalizedRecord();
        $record->sourceType = 'site';
        $record->provider = 'librenms';
        $record->externalId = $raw['external_id'] ?? 'site-' . md5($raw['name'] ?? '');
        $record->name = $raw['name'] ?? 'Unknown Site';
        $record->metadata = $raw;

        return $record;
    }

    /**
     * Extract interfaces from LibreNMS device
     * Note: This requires fetching ports from the API
     */
    protected function extractInterfacesFromDevice(array $device): array
    {
        $interfaces = [];
        $deviceId = $device['device_id'] ?? null;

        if (!$deviceId) {
            return $interfaces;
        }

        try {
            $ports = $this->fetchInterfaces($deviceId);
            foreach ($ports as $port) {
                $interface = [
                    'name' => $port['ifName'] ?? $port['ifDescr'] ?? 'unknown',
                    'display_name' => $port['ifDescr'] ?? $port['ifName'] ?? null,
                    'description' => $port['ifAlias'] ?? null,
                    'type' => $port['ifType'] ?? null,
                    'mac_address' => $port['ifPhysAddress'] ?? null,
                    'speed' => isset($port['ifSpeed']) ? (int) $port['ifSpeed'] : null,
                    'status' => $port['ifOperStatus'] ?? null,
                    'provider' => 'librenms',
                    'external_type' => 'port',
                    'external_id' => (string) ($port['port_id'] ?? null),
                    'metadata' => $port,
                    'ip_addresses' => $this->extractIpAddressesFromPort($port),
                ];
                $interfaces[] = $interface;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch LibreNMS interfaces', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
        }

        return $interfaces;
    }

    /**
     * Fetch interfaces for a device from LibreNMS
     */
    protected function fetchInterfaces(string $deviceId): array
    {
        $result = $this->client->getDevicePorts($deviceId);
        return $result['ports'] ?? [];
    }

    /**
     * Extract IP addresses from a port
     */
    protected function extractIpAddressesFromPort(array $port): array
    {
        $ips = [];

        if (!empty($port['ip'])) {
            $ips[] = [
                'ip' => $port['ip'],
                'prefix' => $port['mask'] ?? null,
                'is_primary' => true,
                'is_management' => false,
                'provider' => 'librenms',
                'external_type' => 'port_ip',
                'external_id' => $port['port_id'] . '_ip',
                'metadata' => ['source' => 'port_ip'],
            ];
        }

        return $ips;
    }
}
