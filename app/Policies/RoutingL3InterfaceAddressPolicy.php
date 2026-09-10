<?php

namespace App\Policies;

use App\Models\RoutingL3Interface;
use App\Models\RoutingL3InterfaceAddress;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class RoutingL3InterfaceAddressPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.routing-l3-interface-addresses.view');
    }

    public function view(User $user, RoutingL3InterfaceAddress $address): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.routing-l3-interface-addresses.view', $address) && $user->can('view', $address->routingL3Interface);
    }

    public function create(User $user, RoutingL3Interface $interface): bool
    {
        return $user->hasPermissionTo('net.routing-l3-interface-addresses.create') && $user->can('view', $interface);
    }

    public function delete(User $user, RoutingL3InterfaceAddress $address): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.routing-l3-interface-addresses.delete', $address) && $user->can('view', $address->routingL3Interface);
    }
}
