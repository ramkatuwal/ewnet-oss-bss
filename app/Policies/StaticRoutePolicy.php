<?php

namespace App\Policies;

use App\Models\RoutingInstance;
use App\Models\StaticRoute;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class StaticRoutePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.static-routes.view');
    }

    public function view(User $user, StaticRoute $route): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.static-routes.view', $route) && $user->can('view', $route->routingInstance);
    }

    public function create(User $user, RoutingInstance $instance): bool
    {
        return $user->hasPermissionTo('net.static-routes.create') && $user->can('view', $instance);
    }

    public function delete(User $user, StaticRoute $route): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.static-routes.delete', $route) && $user->can('view', $route->routingInstance);
    }
}
