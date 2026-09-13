<?php

namespace App\Services\Observations\Sources;

use App\Contracts\ProviderObservationSource;
use App\Dto\Observations\ObservedDevice;
use App\Dto\Observations\ObservedInterface;
use App\Integrations\Providers\Uisp\UispClient;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

/**
 * UISP observation source.
 *
 * Fetches per-device interfaces (name, MAC, MTU where reported) and
 * normalizes them into provider-neutral DTOs. UISP does not currently expose
 * VLAN observations, so the vlan set is empty.
 */
class UispObservationSource implements ProviderObservationSource
{
    public function __construct(private readonly UispClient $client) {}

    public function fetch(Integration $integration): array
    {
        $devices = $this->client->getDevices();

        $devices = is_array($devices) ? $devices : [];

        $snapshots = [];

        foreach ($devices as $device) {
            $snapshots[] = $this->fetchDevice($integration, $device);
        }

        return $snapshots;
    }

    private function fetchDevice(Integration $integration, mixed $device): ObservedDevice
    {
        $deviceId = $device['identification']['id'] ?? $device['id'] ?? null;
        if ($deviceId === null) {
            return new ObservedDevice('unknown', 'uisp');
        }

        $interfaces = [];

        try {
            $rawInterfaces = $this->client->getDeviceInterfaces((string) $deviceId);
        } catch (\Throwable $e) {
            Log::warning("UISP observation: device {$deviceId} interfaces unavailable", [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return new ObservedDevice($deviceId, 'uisp');
        }

        // UISP v2.1 returns a plain array of interface objects. Some
        // configurations wrap them under a `data` key — handle both.
        if (is_array($rawInterfaces) && isset($rawInterfaces['data']) && is_array($rawInterfaces['data'])) {
            $rawInterfaces = $rawInterfaces['data'];
        }

        foreach (is_array($rawInterfaces) ? $rawInterfaces : [] as $entry) {
            $attributes = is_array($entry) && isset($entry['attributes']) && is_array($entry['attributes'])
                ? $entry['attributes']
                : (is_array($entry) ? $entry : []);

            $name = $attributes['name'] ?? $attributes['ifName'] ?? null;
            $id = is_array($entry) && isset($entry['identification']['id'])
                ? $entry['identification']['id']
                : (isset($entry['id']) ? $entry['id'] : null);

            if ($name === null || $name === '') {
                continue;
            }

            $interfaces[] = new ObservedInterface(
                name: (string) $name,
                displayName: null,
                description: null,
                type: $this->nullableString($attributes['type'] ?? null),
                speed: null,
                adminStatus: null,
                operStatus: null,
                macAddress: $this->normalizeMac($attributes['mac'] ?? null),
                mtu: $this->normalizeInt($attributes['mtu'] ?? null),
                accessVlan: null,
                externalId: $id,
                externalType: 'interface',
                ifIndex: null,
                addresses: [],
                isManagement: false,
                metadata: [],
            );
        }

        return new ObservedDevice($deviceId, 'uisp', $interfaces);
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

    private function normalizeMac(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $mac = trim((string) $value);

        if (preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac)) {
            return strtoupper($mac);
        }

        if (preg_match('/^[0-9A-Fa-f]{12}$/', $mac)) {
            return strtoupper(implode(':', str_split($mac, 2)));
        }

        return null;
    }
}
