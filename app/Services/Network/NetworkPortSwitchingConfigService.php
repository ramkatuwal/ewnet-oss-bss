<?php

namespace App\Services\Network;

use App\Models\Asset;
use App\Models\NetworkPort;
use App\Models\NetworkPortSwitchingConfig;
use App\Models\NetworkPortVlanMembership;
use App\Models\User;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NetworkPortSwitchingConfigService
{
    public function replace(NetworkPort $port, array $attributes, User $user): array
    {
        try {
            return DB::transaction(function () use ($port, $attributes, $user) {
                $asset = Asset::withTrashed()->lockForUpdate()->findOrFail($port->asset_id);
                $port = NetworkPort::withTrashed()->lockForUpdate()->findOrFail($port->id);
                $this->validatePort($port, $asset);

                $existing = NetworkPortSwitchingConfig::where('network_port_id', $port->id)
                    ->with(['memberships.vlan'])
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    $existing->memberships->each(fn (NetworkPortVlanMembership $membership) => $membership->lockForUpdate()->find($membership->id));
                }

                $memberships = $this->canonicalMemberships($attributes['memberships']);
                $vlans = Vlan::whereIn('id', array_column($memberships, 'vlan_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($vlans->count() !== count($memberships)) {
                    throw ValidationException::withMessages(['memberships' => 'Every selected VLAN must be live and available.']);
                }
                foreach ($vlans as $vlan) {
                    if ($vlan->trashed() || (int) $vlan->company_id !== (int) $port->company_id) {
                        throw ValidationException::withMessages(['memberships' => 'Every selected VLAN must be live and belong to the port company.']);
                    }
                }
                $this->validateDesiredState($attributes['mode'], $memberships);

                if ($existing && $this->isEquivalent($existing, $attributes['mode'], $attributes['metadata'] ?? null, $memberships)) {
                    return ['configuration' => $existing, 'changed' => false, 'replaced' => false];
                }

                if ($existing) {
                    foreach ($existing->memberships as $membership) {
                        $membership->update(['updated_by' => $user->id]);
                        $membership->delete();
                    }
                    $existing->update(['updated_by' => $user->id]);
                    $existing->delete();
                }

                $configuration = NetworkPortSwitchingConfig::create([
                    'network_port_id' => $port->id,
                    'company_id' => $port->company_id,
                    'mode' => $attributes['mode'],
                    'metadata' => $attributes['metadata'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                foreach ($memberships as $membership) {
                    NetworkPortVlanMembership::create([
                        'network_port_switching_config_id' => $configuration->id,
                        'vlan_id' => $membership['vlan_id'],
                        'company_id' => $port->company_id,
                        'tagging' => $membership['tagging'],
                        'metadata' => $membership['metadata'] ?? null,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }

                return ['configuration' => $configuration->load(['networkPort.asset', 'memberships.vlan']), 'changed' => true, 'replaced' => $existing !== null];
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['switching_configuration' => 'Cannot apply this switching configuration due to data integrity constraints.']);
        }
    }

    public function retire(NetworkPort $port, User $user): void
    {
        try {
            DB::transaction(function () use ($port, $user) {
                $asset = Asset::withTrashed()->lockForUpdate()->findOrFail($port->asset_id);
                $port = NetworkPort::withTrashed()->lockForUpdate()->findOrFail($port->id);
                $this->validatePort($port, $asset);
                $configuration = NetworkPortSwitchingConfig::where('network_port_id', $port->id)->with('memberships')->lockForUpdate()->firstOrFail();
                foreach ($configuration->memberships as $membership) {
                    $membership->lockForUpdate()->findOrFail($membership->id)->update(['updated_by' => $user->id]);
                    $membership->delete();
                }
                $configuration->update(['updated_by' => $user->id]);
                $configuration->delete();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['switching_configuration' => 'Cannot retire this switching configuration due to data integrity constraints.']);
        }
    }

    private function validatePort(NetworkPort $port, Asset $asset): void
    {
        if ($port->trashed() || $asset->trashed() || strtoupper($asset->category) !== 'NETWORK'
            || (int) $port->company_id !== (int) $asset->company_id) {
            throw ValidationException::withMessages(['network_port' => 'Select a live same-company network port on a NETWORK asset.']);
        }
    }

    private function canonicalMemberships(array $memberships): array
    {
        $canonical = array_map(fn (array $membership) => [
            'vlan_id' => (int) $membership['vlan_id'],
            'tagging' => $membership['tagging'],
            'metadata' => $membership['metadata'] ?? null,
        ], $memberships);
        usort($canonical, fn (array $a, array $b) => $a['vlan_id'] <=> $b['vlan_id']);

        return $canonical;
    }

    private function validateDesiredState(string $mode, array $memberships): void
    {
        if (count(array_unique(array_column($memberships, 'vlan_id'))) !== count($memberships)) {
            throw ValidationException::withMessages(['memberships' => 'A VLAN can appear only once in a switching configuration.']);
        }
        $untagged = count(array_filter($memberships, fn (array $membership) => $membership['tagging'] === 'untagged'));
        if ($mode === 'access' && (count($memberships) !== 1 || $untagged !== 1)) {
            throw ValidationException::withMessages(['memberships' => 'Access mode requires exactly one untagged VLAN membership.']);
        }
        if ($mode === 'trunk' && $untagged > 1) {
            throw ValidationException::withMessages(['memberships' => 'Trunk mode permits at most one untagged VLAN membership.']);
        }
    }

    private function isEquivalent(NetworkPortSwitchingConfig $existing, string $mode, ?array $metadata, array $memberships): bool
    {
        $current = $this->canonicalMemberships($existing->memberships->map(fn (NetworkPortVlanMembership $membership) => [
            'vlan_id' => $membership->vlan_id,
            'tagging' => $membership->tagging,
            'metadata' => $membership->metadata,
        ])->all());

        return $existing->mode === $mode && $this->normalize($existing->metadata) === $this->normalize($metadata) && $this->normalize($current) === $this->normalize($memberships);
    }

    private function normalize(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = json_decode($this->normalize($item), true);
                }
            }
            unset($item);
            if (! array_is_list($value)) {
                ksort($value);
            }
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
