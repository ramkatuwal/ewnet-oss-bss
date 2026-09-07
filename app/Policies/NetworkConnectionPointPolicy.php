<?php

namespace App\Policies;

use App\Models\NetworkConnectionPoint;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class NetworkConnectionPointPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.connection-points.view');
    }

    public function view(User $user, NetworkConnectionPoint $point): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.connection-points.view', $point);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fim.connection-points.create');
    }

    public function update(User $user, NetworkConnectionPoint $point): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.connection-points.update', $point);
    }

    public function delete(User $user, NetworkConnectionPoint $point): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.connection-points.delete', $point);
    }
}
