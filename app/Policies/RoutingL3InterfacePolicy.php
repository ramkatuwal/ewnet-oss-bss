<?php

namespace App\Policies;

use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class RoutingL3InterfacePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.routing-l3-interfaces.view');
    }

    public function view(User $user, RoutingL3Interface $interface): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.routing-l3-interfaces.view', $interface) && $user->can('view', $interface->routingInstance);
    }

    public function create(User $user, RoutingInstance $instance): bool
    {
        return $user->hasPermissionTo('net.routing-l3-interfaces.create') && $user->can('view', $instance);
    }

    public function delete(User $user, RoutingL3Interface $interface): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.routing-l3-interfaces.delete', $interface) && $user->can('view', $interface->routingInstance);
    }
}
