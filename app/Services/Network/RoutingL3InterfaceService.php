<?php

namespace App\Services\Network;

use App\Models\NetworkPort;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\RoutingL3InterfaceAddress;
use App\Models\User;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoutingL3InterfaceService
{
    public function create(RoutingInstance $instance, array $attributes, User $user): RoutingL3Interface
    {
        try {
            return DB::transaction(function () use ($instance, $attributes, $user) {
                $instance = RoutingInstance::withTrashed()->lockForUpdate()->findOrFail($instance->id);
                if ($instance->trashed()) {
                    throw ValidationException::withMessages(['routing_instance' => 'Select a live routing instance.']);
                }
                foreach (array_filter([$attributes['network_port_id'] ?? null, $attributes['vlan_id'] ?? null, $attributes['parent_routing_l3_interface_id'] ?? null]) as $id) {
                    DB::select('SELECT pg_advisory_xact_lock(?)', [(int) $id]);
                }
                $port = isset($attributes['network_port_id']) ? NetworkPort::withTrashed()->lockForUpdate()->find($attributes['network_port_id']) : null;
                $vlan = isset($attributes['vlan_id']) ? Vlan::withTrashed()->lockForUpdate()->find($attributes['vlan_id']) : null;
                $parent = isset($attributes['parent_routing_l3_interface_id']) ? RoutingL3Interface::withTrashed()->lockForUpdate()->find($attributes['parent_routing_l3_interface_id']) : null;
                $this->validateShape($instance, $attributes, $port, $vlan, $parent);

                return RoutingL3Interface::create(['routing_instance_id' => $instance->id, 'asset_id' => $instance->asset_id, 'company_id' => $instance->company_id, ...$attributes, 'created_by' => $user->id, 'updated_by' => $user->id]);
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['routing_l3_interface' => 'Cannot create this L3 interface due to data integrity constraints.']);
        }
    }

    public function retire(RoutingL3Interface $interface, User $user): void
    {
        try {
            DB::transaction(function () use ($interface, $user) {
                $interface = RoutingL3Interface::lockForUpdate()->findOrFail($interface->id);
                if (RoutingL3Interface::where('parent_routing_l3_interface_id', $interface->id)->exists()) {
                    throw ValidationException::withMessages(['routing_l3_interface' => 'Retire child subinterfaces first.']);
                }
                if (RoutingL3InterfaceAddress::where('routing_l3_interface_id', $interface->id)->exists()) {
                    throw ValidationException::withMessages(['routing_l3_interface' => 'Retire authoritative addresses first.']);
                }
                $interface->update(['updated_by' => $user->id]);
                $interface->delete();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['routing_l3_interface' => 'Cannot retire this L3 interface due to data integrity constraints.']);
        }
    }

    private function validateShape(RoutingInstance $instance, array $a, ?NetworkPort $port, ?Vlan $vlan, ?RoutingL3Interface $parent): void
    {
        $kind = $a['kind'];
        $hasPort = $port !== null;
        $hasVlan = $vlan !== null;
        $hasParent = $parent !== null;
        if (($kind === 'physical' && (! $hasPort || $hasVlan || $hasParent)) || ($kind === 'svi' && ($hasPort || ! $hasVlan || $hasParent)) || ($kind === 'subinterface' && ($hasPort || ! $hasVlan || ! $hasParent)) || ($kind === 'loopback' && ($hasPort || $hasVlan || $hasParent))) {
            throw ValidationException::withMessages(['kind' => 'The selected L3 interface kind has an invalid anchor shape.']);
        }
        if ($port && ($port->trashed() || (int) $port->asset_id !== (int) $instance->asset_id || (int) $port->company_id !== (int) $instance->company_id)) {
            throw ValidationException::withMessages(['network_port_id' => 'Select a live same-asset company network port.']);
        }
        if ($vlan && ($vlan->trashed() || (int) $vlan->company_id !== (int) $instance->company_id)) {
            throw ValidationException::withMessages(['vlan_id' => 'Select a live same-company VLAN.']);
        }
        if ($parent && ($parent->trashed() || $parent->kind !== 'physical' || (int) $parent->routing_instance_id !== (int) $instance->id || (int) $parent->asset_id !== (int) $instance->asset_id || (int) $parent->company_id !== (int) $instance->company_id)) {
            throw ValidationException::withMessages(['parent_routing_l3_interface_id' => 'Select a live physical interface in this routing instance.']);
        }
    }
}
