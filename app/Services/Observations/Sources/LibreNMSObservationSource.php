<?php

namespace App\Services\Observations\Sources;

use App\Contracts\ProviderObservationSource;
use App\Dto\Observations\ObservedAddress;
use App\Dto\Observations\ObservedDevice;
use App\Dto\Observations\ObservedInterface;
use App\Dto\Observations\ObservedVlan;
use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

/**
 * LibreNMS observation source.
 *
 * Fetches device ports (with MAC, access VLAN, MTU, speeds and status),
 * device-level VLANs where polled, and optionally per-port IP addresses.
 * All records are normalized into provider-neutral DTOs.
 */
class LibreNMSObservationSource implements ProviderObservationSource
{
    private const PORT_COLUMNS = [
        'port_id', 'device_id', 'ifIndex', 'ifName', 'ifDescr', 'ifAlias',
        'ifType', 'ifSpeed', 'ifAdminStatus', 'ifOperStatus',
        'ifPhysAddress', 'ifVlan', 'ifMtu',
    ];

    /** Bound the number of per-port IP lookups per device. */
    private const MAX_IP_LOOKUPS_PER_DEVICE = 50;

    public function __construct(private readonly LibreNMSClient $client) {}

    public function fetch(Integration $integration): array
    {
        $devices = $this->client->listDevices();

        $devices = is_array($devices) ? $devices : [];

        $observeIps = (bool) ($integration->configuration['observe_ip_addresses'] ?? false);

        $snapshots = [];

        foreach ($devices as $device) {
            $deviceId = $device['device_id'] ?? null;
            $snapshots[] = $this->fetchDevice($integration, $deviceId, $observeIps);
        }

        return $snapshots;
    }

    private function fetchDevice(Integration $integration, string|int|null $deviceId, bool $observeIps): ObservedDevice
    {
        if ($deviceId === null) {
            return new ObservedDevice('unknown', 'librenms');
        }

        $interfaces = [];
        $vlans = [];

        try {
            $ports = $this->client->getDevicePorts((string) $deviceId, self::PORT_COLUMNS);
        } catch (\Throwable $e) {
            Log::warning("LibreNMS observation: device {$deviceId} ports unavailable", [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return new ObservedDevice($deviceId, 'librenms');
        }

        $seenVlans = [];
        $ipLookups = 0;

        foreach ($ports['ports'] as $port) {
            $name = $port['ifName'] ?? null;
            if ($name === null || $name === '') {
                continue;
            }

            $accessVlan = $this->normalizeVid($port['ifVlan'] ?? null);
            $observableState = $this->interfaceState($port);

            $addresses = [];
            if ($observeIps && $ipLookups < self::MAX_IP_LOOKUPS_PER_DEVICE && $observableState === 'up') {
                $ipLookups++;
                $addresses = $this->fetchAddresses($port['port_id'] ?? null);
            }

            $interfaces[] = new ObservedInterface(
                name: (string) $name,
                displayName: $this->nullableString($port['ifAlias'] ?? null),
                description: $this->nullableString($port['ifDescr'] ?? null),
                type: $this->nullableString($port['ifType'] ?? null),
                speed: $this->normalizeInt($port['ifSpeed'] ?? null),
                adminStatus: $this->nullableString($port['ifAdminStatus'] ?? null),
                operStatus: $observableState,
                macAddress: $this->normalizeMac($port['ifPhysAddress'] ?? null),
                mtu: $this->normalizeInt($port['ifMtu'] ?? null),
                accessVlan: $accessVlan,
                externalId: $port['port_id'] ?? null,
                externalType: 'port',
                ifIndex: $this->normalizeInt($port['ifIndex'] ?? null),
                addresses: $addresses,
                isManagement: false,
                metadata: [
                    'ifIndex' => $port['ifIndex'] ?? null,
                    'admin_status' => $port['ifAdminStatus'] ?? null,
                ],
            );

            if ($accessVlan !== null && ! isset($seenVlans[$accessVlan])) {
                $seenVlans[$accessVlan] = new ObservedVlan(
                    vid: $accessVlan,
                    name: $this->deriveVlanName((string) $name, $accessVlan),
                    vlanType: 'access',
                    externalType: 'interface',
                    externalId: null,
                    metadata: ['source' => 'port_access_vlan'],
                );
            }
        }

        $deviceVlans = $this->fetchDeviceVlans($integration, (string) $deviceId);
        foreach ($deviceVlans as $vlan) {
            $vid = $this->normalizeVid($vlan['vlan_num'] ?? $vlan['vid'] ?? $vlan['vlan'] ?? null);
            if ($vid === null) {
                continue;
            }
            $seenVlans[$vid] = new ObservedVlan(
                vid: $vid,
                name: $vlan['vlan_name'] ?? $vlan['name'] ?? null,
                vlanType: $vlan['vlan_type'] ?? $vlan['type'] ?? 'unknown',
                externalType: 'device',
                externalId: $vid,
                metadata: ['source' => 'device_vlan_list'],
            );
        }

        $vlans = array_values($seenVlans);

        return new ObservedDevice($deviceId, 'librenms', $interfaces, $vlans);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchDeviceVlans(Integration $integration, string $deviceId): array
    {
        try {
            $result = $this->client->getDeviceVlans($deviceId);

            return is_array($result['vlans']) ? $result['vlans'] : [];
        } catch (\Throwable $e) {
            Log::debug("LibreNMS observation: device {$deviceId} vlan list unavailable", [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, ObservedAddress>
     */
    private function fetchAddresses(string|int|null $portId): array
    {
        if ($portId === null) {
            return [];
        }

        try {
            $result = $this->client->getPortIpAddresses((int) $portId);
        } catch (\Throwable $e) {
            return [];
        }

        $addresses = [];

        foreach (is_array($result['addresses']) ? $result['addresses'] : [] as $entry) {
            $addr = $entry['ip'] ?? $entry['address'] ?? null;
            if ($addr === null || $addr === '') {
                continue;
            }

            $addresses[] = new ObservedAddress(
                ip: $addr,
                prefixLength: $this->normalizeInt($entry['cidr'] ?? $entry['prefix_length'] ?? $entry['mask'] ?? null),
                family: str_contains($addr, ':') ? 6 : 4,
                isPrimary: false,
                isManagement: false,
                externalType: 'port-ip',
                externalId: $portId,
            );
        }

        return $addresses;
    }

    private function interfaceState(array $port): ?string
    {
        if (! empty($port['ifOperStatus'])) {
            return (string) $port['ifOperStatus'];
        }

        return $this->nullableString($port['ifAdminStatus'] ?? null);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeVid(mixed $value): ?int
    {
        $int = $this->normalizeInt($value);
        if ($int === null || $int < 1 || $int > 4094) {
            return null;
        }

        return $int;
    }

    /**
     * LibreNMS returns hex MACs without separators (e.g. 488f5a0ff1c6).
     * Normalize to XX:XX:XX:XX:XX:XX. Null when unrecognizable.
     */
    private function normalizeMac(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $mac = trim((string) $value);

        if (str_contains($mac, ':')) {
            return preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac)
                ? strtoupper($mac)
                : null;
        }

        if (preg_match('/^[0-9A-Fa-f]{12}$/', $mac)) {
            return strtoupper(implode(':', str_split($mac, 2)));
        }

        return null;
    }

    private function deriveVlanName(string $ifName, int $vid): ?string
    {
        if (preg_match('/^(?:vlan|br)[_\s-]*'.$vid.'(?:[_\s-]+(.+))?$/i', $ifName, $m)) {
            return isset($m[1]) && $m[1] !== '' ? $m[1] : null;
        }

        return null;
    }
}
