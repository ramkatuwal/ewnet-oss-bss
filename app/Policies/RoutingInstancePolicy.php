<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\RoutingInstance;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class RoutingInstancePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.routing-instances.view');
    }

    public function view(User $user, RoutingInstance $routingInstance): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.routing-instances.view', $routingInstance)
            && $user->can('view', $routingInstance->asset);
    }

    public function create(User $user, Asset $asset): bool
    {
        return $user->hasPermissionTo('net.routing-instances.create') && $user->can('view', $asset);
    }

    public function delete(User $user, RoutingInstance $routingInstance): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.routing-instances.delete', $routingInstance)
            && $user->can('view', $routingInstance->asset);
    }
}
