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
            // LibreNMSClient::listDevices returns the 'devices' array directly
            return $this->client->listDevices(['type' => 'all']);
        } catch (\Throwable $e) {
            Log::error('LibreNMS fetchDevices failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function fetchSites(): array
    {
        // LibreNMS doesn't have a native "Site" endpoint like UISP.
        // We derive sites from the 'location' or 'sysLocation' of devices, 
        // or use the existing SiteMappingService logic if available.
        // For now, we will fetch all devices and extract unique locations.
        try {
            $devices = $this->client->listDevices(['type' => 'all']);
            $sites = [];
            $seenLocations = [];

            foreach ($devices as $device) {
                $location = $device['sysLocation'] ?? $device['location'] ?? null;
                if ($location && !isset($seenLocations[$location])) {
                    $seenLocations[$location] = true;
                    $sites[] = [
                        'id' => md5($location), // Generate a stable ID for the location
                        'name' => $location,
                        'description' => "Derived from LibreNMS location: {$location}",
                        'device_count' => 0 // Will be calculated if needed
                    ];
                }
            }
            return array_values($sites);
        } catch (\Throwable $e) {
            Log::error('LibreNMS fetchSites failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function normalizeDevice(array $raw): NormalizedRecord
    {
        $id = $raw['device_id'] ?? null;
        if (!$id) throw new \InvalidArgumentException('LibreNMS device missing device_id');

        $name = $raw['sysName'] ?? $raw['hostname'] ?? 'Unknown Device';
        $hostname = $raw['hostname'] ?? null;
        
        // IP Address extraction
        $ip = $raw['ip'] ?? $raw['overwrite_ip'] ?? null;
        
        // MAC Address: LibreNMS devices don't always have a primary MAC in the list.
        // We might need to look at ports, but for preview, we'll leave it null unless available.
        $mac = $raw['mac_address'] ?? null; 

        // Model/Vendor
        $hardware = $raw['hardware'] ?? '';
        $vendor = $raw['vendor'] ?? '';
        $os = $raw['os'] ?? '';
        
        // Status
        $status = match ($raw['status'] ?? 0) {
            1 => 'up',
            0 => 'down',
            default => 'unknown'
        };

        // Site Reference
        $siteName = $raw['sysLocation'] ?? $raw['location'] ?? null;

        return new NormalizedRecord(
            provider: 'librenms',
            sourceType: 'device',
            externalId: (string) $id,
            name: $name,
            description: $raw['sysDescr'] ?? null,
            ipAddress: $this->normalizeIp($ip),
            macAddress: $mac ? strtolower($mac) : null,
            serialNumber: $raw['serial'] ?? null,
            manufacturer: $vendor,
            model: $hardware,
            firmwareVersion: $os,
            status: $status,
            metadata: [
                'hostname' => $hostname,
                'site_name' => $siteName,
                'type' => $raw['type'] ?? null,
            ],
            rawSource: $raw
        );
    }

    public function normalizeSite(array $raw): NormalizedRecord
    {
        $id = $raw['id'] ?? null;
        if (!$id) throw new \InvalidArgumentException('LibreNMS site missing ID');

        return new NormalizedRecord(
            provider: 'librenms',
            sourceType: 'site',
            externalId: (string) $id,
            name: $raw['name'] ?? 'Unknown Site',
            description: $raw['description'] ?? null,
            ipAddress: null,
            macAddress: null,
            serialNumber: null,
            manufacturer: null,
            model: null,
            status: 'active',
            metadata: [
                'address' => null,
                'latitude' => null,
                'longitude' => null,
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
