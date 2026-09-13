<?php

namespace App\Services\Observations;

use App\Contracts\ProviderObservationSource;
use App\Dto\Observations\ObservedAddress;
use App\Dto\Observations\ObservedDevice;
use App\Dto\Observations\ObservedInterface;
use App\Dto\Observations\ObservedVlan;
use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use App\Integrations\Providers\Uisp\UispClient;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\AssetInterface;
use App\Models\Integration;
use App\Models\IpAddress;
use App\Models\NetworkPort;
use App\Models\ObservedVlan as ObservedVlanModel;
use App\Models\Vlan;
use App\Services\Observations\Sources\LibreNMSObservationSource;
use App\Services\Observations\Sources\UispObservationSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates provider observation sync:
 *
 *   provider → source (DTO normalization) → asset mapping → observation store
 *     → reconciliation (read-only FK links to authoritative ports/vlans)
 *     → stale detection
 *
 * Observations are NEVER used to author authoritative network intent.
 * Reconciliation only links observed rows to existing authoritative rows.
 */
class ObservationSyncService
{
    public const CATEGORIES = ['interfaces', 'vlans', 'all'];

    private const LOCK_SECONDS = 1800;

    public function run(Integration $integration, string $category): array
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException("Unknown observation sync category: {$category}");
        }

        $lockName = "observation-sync:{$integration->id}:{$category}";
        $lock = Cache::lock($lockName, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return [
                'locked' => true,
                'devices_seen' => 0,
                'records_created' => 0,
                'records_updated' => 0,
                'records_skipped' => 0,
                'records_failed' => 0,
                'stale_interfaces' => 0,
                'stale_vlans' => 0,
                'stale_addresses' => 0,
                'ports_reconciled' => 0,
                'error' => 'Another observation sync is already running for this integration.',
            ];
        }

        try {
            return $this->synchronize($integration, $category);
        } finally {
            $lock->release();
        }
    }

    private function synchronize(Integration $integration, string $category): array
    {
        $source = $this->resolveSource($integration);
        $runAt = now();

        $counters = [
            'devices_seen' => 0,
            'skipped_assets' => 0,
            'interfaces_created' => 0,
            'interfaces_updated' => 0,
            'interfaces_failed' => 0,
            'addresses_created' => 0,
            'addresses_updated' => 0,
            'vlans_created' => 0,
            'vlans_updated' => 0,
            'ports_reconciled' => 0,
            'stale_interfaces' => 0,
            'stale_vlans' => 0,
            'stale_addresses' => 0,
        ];

        $visitedAssetIds = [];
        $visitedInterfaceIds = [];

        $devices = $source->fetch($integration);
        $counters['devices_seen'] = count($devices);

        $processInterfaces = $category !== 'vlans';
        $processVlans = $category !== 'interfaces';

        foreach ($devices as $device) {
            $asset = $this->resolveAsset($integration, $device->externalId);
            if ($asset === null) {
                $counters['skipped_assets']++;

                continue;
            }

            $visitedAssetIds[] = $asset->id;

            if ($processInterfaces) {
                $this->processInterfaces($integration, $asset, $device, $counters, $visitedInterfaceIds, $runAt);
            }

            if ($processVlans) {
                $this->processVlans($integration, $asset, $device, $counters, $runAt);
            }
        }

        if ($processInterfaces) {
            $counters['stale_interfaces'] = AssetInterface::query()
                ->whereIn('asset_id', $visitedAssetIds)
                ->where('integration_id', $integration->id)
                ->where('provider', $integration->provider)
                ->where('observation_status', 'observed')
                ->where('last_seen_at', '<', $runAt)
                ->update(['observation_status' => 'stale']);

            if ($visitedInterfaceIds !== []) {
                $counters['stale_addresses'] = IpAddress::query()
                    ->whereIn('asset_interface_id', $visitedInterfaceIds)
                    ->where('provider', $integration->provider)
                    ->where('observation_status', 'observed')
                    ->where('last_seen_at', '<', $runAt)
                    ->update(['observation_status' => 'stale']);
            }
        }

        if ($processVlans) {
            $counters['stale_vlans'] = ObservedVlanModel::query()
                ->whereIn('asset_id', $visitedAssetIds)
                ->where('integration_id', $integration->id)
                ->where('observation_status', 'observed')
                ->where('last_seen_at', '<', $runAt)
                ->update(['observation_status' => 'stale']);
        }

        Log::info('Observation sync completed', [
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'category' => $category,
            'counters' => $counters,
        ]);

        return $this->summary($counters);
    }

    /**
     * @param  array<string, int>  $counters
     * @param  array<int, int>  $visitedInterfaceIds
     */
    private function processInterfaces(
        Integration $integration,
        Asset $asset,
        ObservedDevice $device,
        array &$counters,
        array &$visitedInterfaceIds,
        Carbon $runAt,
    ): void {
        $existingByName = AssetInterface::where('asset_id', $asset->id)->get()
            ->keyBy(fn (AssetInterface $row) => mb_strtolower($row->name));
        $existingByExternal = AssetInterface::where('asset_id', $asset->id)->get()
            ->filter(fn (AssetInterface $row) => $row->external_type !== null && $row->external_id !== null)
            ->keyBy(fn (AssetInterface $row) => $row->integration_id.'|'.$row->external_type.'|'.$row->external_id);

        $portLookup = $this->buildPortLookup($asset);

        foreach ($device->interfaces as $dto) {
            try {
                $row = $this->findInterfaceRow($integration, $asset, $dto, $existingByName, $existingByExternal);

                $data = $this->interfaceRowData($integration, $dto, $portLookup, $runAt, $counters);

                if ($row === null) {
                    $row = new AssetInterface(array_merge([
                        'asset_id' => $asset->id,
                        'name' => $dto->name,
                        'provider' => $integration->provider,
                        'integration_id' => $integration->id,
                        'first_seen_at' => $runAt,
                    ], $data));

                    $row->save();
                    $counters['interfaces_created']++;

                    $existingByName[mb_strtolower($row->name)] = $row;
                    if ($row->external_type !== null && $row->external_id !== null) {
                        $existingByExternal[$row->integration_id.'|'.$row->external_type.'|'.$row->external_id] = $row;
                    }
                } else {
                    $nameCollision = AssetInterface::query()
                        ->where('asset_id', $asset->id)
                        ->where('name', $dto->name)
                        ->where('id', '!=', $row->id)
                        ->exists();

                    if ($nameCollision) {
                        unset($data['name']);
                    } elseif (mb_strtolower($row->name) !== mb_strtolower($dto->name)) {
                        unset($existingByName[mb_strtolower($row->name)]);
                        $data['name'] = $dto->name;
                    }

                    $row->fill($data);
                    $row->save();
                    $counters['interfaces_updated']++;

                    $existingByName[mb_strtolower($row->name)] = $row;
                    if ($row->external_type !== null && $row->external_id !== null) {
                        $existingByExternal[$row->integration_id.'|'.$row->external_type.'|'.$row->external_id] = $row;
                    }
                }

                $visitedInterfaceIds[] = $row->id;

                foreach ($dto->addresses as $address) {
                    $this->upsertAddress($row->id, $integration, $address, $counters, $runAt);
                }
            } catch (\Throwable $e) {
                $counters['interfaces_failed']++;
                Log::warning('Observation sync: interface failed', [
                    'asset_id' => $asset->id,
                    'interface' => $dto->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, int>  $counters
     */
    private function processVlans(Integration $integration, Asset $asset, ObservedDevice $device, array &$counters, Carbon $runAt): void
    {
        $reconciledCache = [];
        $existingByVid = ObservedVlanModel::where('integration_id', $integration->id)
            ->where('asset_id', $asset->id)
            ->get()
            ->keyBy(fn (ObservedVlanModel $row) => $row->vid);

        foreach ($device->vlans as $dto) {
            try {
                $row = $existingByVid->get($dto->vid);

                $reconciledVlan = $reconciledCache[$dto->vid] ?? null;
                if ($reconciledVlan === null && $asset->company_id !== null) {
                    $reconciledVlan = Vlan::query()
                        ->where('company_id', $asset->company_id)
                        ->where('vid', $dto->vid)
                        ->whereNull('deleted_at')
                        ->first();
                    $reconciledCache[$dto->vid] = $reconciledVlan;
                }

                $metadata = array_merge($dto->metadata, ['provider' => $integration->provider]);

                $data = [
                    'vid' => $dto->vid,
                    'name' => $dto->name,
                    'vlan_type' => $dto->vlanType,
                    'provider' => $integration->provider,
                    'external_type' => $dto->externalType,
                    'external_id' => $dto->externalId !== null ? (string) $dto->externalId : null,
                    'observation_status' => 'observed',
                    'metadata' => $metadata,
                    'reconciled_vlan_id' => $reconciledVlan?->id,
                    'asset_interface_id' => $this->resolveObservedInterfaceId($integration, $asset, $dto),
                    'last_seen_at' => $runAt,
                ];

                if ($row === null) {
                    $row = ObservedVlanModel::create(array_merge([
                        'integration_id' => $integration->id,
                        'asset_id' => $asset->id,
                        'first_seen_at' => $runAt,
                    ], $data));
                    $existingByVid->put($dto->vid, $row);
                    $counters['vlans_created']++;
                } else {
                    $row->fill($data);
                    $row->save();
                    $counters['vlans_updated']++;
                }
            } catch (\Throwable $e) {
                $counters['interfaces_failed']++;
                Log::warning('Observation sync: vlan failed', [
                    'asset_id' => $asset->id,
                    'vid' => $dto->vid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, int>  $counters
     */
    private function upsertAddress(int $interfaceId, Integration $integration, ObservedAddress $address, array &$counters, Carbon $runAt): void
    {
        try {
            $row = IpAddress::where('asset_interface_id', $interfaceId)
                ->where('ip_address', $address->ip)
                ->first();

            $data = [
                'family' => $address->family,
                'prefix_length' => $address->prefixLength,
                'is_primary' => $address->isPrimary,
                'is_management' => $address->isManagement,
                'provider' => $integration->provider,
                'external_type' => $address->externalType,
                'external_id' => $address->externalId !== null ? (string) $address->externalId : null,
                'metadata' => $address->metadata,
                'observation_status' => 'observed',
                'last_seen_at' => $runAt,
            ];

            if ($row) {
                $row->fill($data);
                $row->save();
                $counters['addresses_updated']++;
            } else {
                IpAddress::create(array_merge([
                    'asset_interface_id' => $interfaceId,
                    'ip_address' => $address->ip,
                    'first_seen_at' => $runAt,
                ], $data));
                $counters['addresses_created']++;
            }
        } catch (\Throwable $e) {
            $counters['interfaces_failed']++;
            Log::warning('Observation sync: address failed', [
                'interface_id' => $interfaceId,
                'ip' => $address->ip,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the interface upsert payload for a DTO.
     *
     * @param  array<string, int>  $portLookup
     * @param  array<string, int>  $counters
     * @return array<string, mixed>
     */
    private function interfaceRowData(
        Integration $integration,
        ObservedInterface $dto,
        array $portLookup,
        Carbon $runAt,
        array &$counters,
    ): array {
        $reconciledPortId = $this->reconcileNetworkPort($dto, $portLookup);
        if ($reconciledPortId !== null) {
            $counters['ports_reconciled']++;
        }

        return [
            'name' => $dto->name,
            'display_name' => $dto->displayName,
            'description' => $dto->description,
            'type' => $dto->type,
            'mac_address' => $dto->macAddress,
            'speed' => $dto->speed,
            'status' => $dto->operStatus ?? $dto->adminStatus,
            'is_management' => $dto->isManagement,
            'provider' => $integration->provider,
            'integration_id' => $integration->id,
            'external_type' => $dto->externalType,
            'external_id' => $dto->externalId !== null ? (string) $dto->externalId : null,
            'observation_status' => 'observed',
            'metadata' => array_merge($dto->metadata, ['access_vlan' => $dto->accessVlan]),
            'reconciled_network_port_id' => $reconciledPortId,
            'last_seen_at' => $runAt,
        ];
    }

    /**
     * @param  array<string, int>  $portLookup  normalized => port id
     */
    private function reconcileNetworkPort(ObservedInterface $dto, array $portLookup): ?int
    {
        $keys = [$dto->name, $dto->displayName, $dto->description];
        $matched = [];

        foreach ($keys as $key) {
            if ($key === null || $key === '') {
                continue;
            }
            $id = $portLookup[self::normalizeKey($key)] ?? null;
            if ($id !== null) {
                $matched[$id] = true;
            }
        }

        if (count($matched) !== 1) {
            return null;
        }

        return array_key_first($matched);
    }

    /**
     * @return array<string, int>
     */
    private function buildPortLookup(Asset $asset): array
    {
        $lookup = [];

        foreach (NetworkPort::where('asset_id', $asset->id)->whereNull('deleted_at')->get(['id', 'port_key', 'name']) as $port) {
            $lookup[self::normalizeKey($port->port_key)] ??= $port->id;
            if ($port->name !== null) {
                $lookup[self::normalizeKey($port->name)] ??= $port->id;
            }
        }

        return $lookup;
    }

    private function normalizeKey(string $key): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(trim($key))) ?? '';
    }

    /**
     * @param  Collection<string, AssetInterface>  $existingByName
     * @param  Collection<string, AssetInterface>  $existingByExternal
     */
    private function findInterfaceRow(
        Integration $integration,
        Asset $asset,
        ObservedInterface $dto,
        $existingByName,
        $existingByExternal,
    ): ?AssetInterface {
        if ($dto->externalId !== null && $dto->externalType !== null) {
            $key = $integration->id.'|'.$dto->externalType.'|'.$dto->externalId;
            $row = $existingByExternal->get($key);
            if ($row !== null) {
                return $row;
            }
        }

        return $existingByName->get(mb_strtolower($dto->name));
    }

    private function resolveAsset(Integration $integration, string|int $externalId): ?Asset
    {
        $reference = AssetExternalReference::where('provider', $integration->provider)
            ->where('external_type', 'device')
            ->where('external_id', (string) $externalId)
            ->where('integration_id', $integration->id)
            ->with('asset')
            ->first();

        return $reference?->asset;
    }

    private function resolveObservedInterfaceId(Integration $integration, Asset $asset, ObservedVlan $dto): ?int
    {
        return null;
    }

    private function resolveSource(Integration $integration): ProviderObservationSource
    {
        return match ($integration->provider) {
            'librenms' => new LibreNMSObservationSource(new LibreNMSClient($integration)),
            'uisp' => new UispObservationSource(new UispClient($integration)),
            default => throw new \InvalidArgumentException(
                "Provider {$integration->provider} does not support observation sync."
            ),
        };
    }

    /**
     * @param  array<string, int>  $counters
     * @return array<string, mixed>
     */
    private function summary(array $counters): array
    {
        $created = $counters['interfaces_created'] + $counters['vlans_created'] + $counters['addresses_created'];
        $updated = $counters['interfaces_updated'] + $counters['vlans_updated'] + $counters['addresses_updated'];

        return array_merge($counters, [
            'records_created' => $created,
            'records_updated' => $updated,
            'records_processed' => $counters['devices_seen'],
            'records_skipped' => $counters['skipped_assets'],
            'records_failed' => $counters['interfaces_failed'],
        ]);
    }
}
