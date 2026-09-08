<?php

namespace App\Policies;

use App\Models\FiberTermination;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class FiberTerminationPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.fiber-terminations.view')
            && $user->hasPermissionTo('fim.fiber-cores.view')
            && $user->hasPermissionTo('fim.connection-points.view');
    }

    public function view(User $user, FiberTermination $termination): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-terminations.view', $termination)
            && $user->can('view', $termination->fiberCore)
            && $user->can('view', $termination->networkConnectionPoint);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fim.fiber-terminations.create');
    }

    public function update(User $user, FiberTermination $termination): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-terminations.update', $termination)
            && $user->can('update', $termination->fiberCore)
            && $user->can('view', $termination->networkConnectionPoint);
    }

    public function delete(User $user, FiberTermination $termination): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-terminations.delete', $termination)
            && $user->can('delete', $termination->fiberCore)
            && $user->can('view', $termination->networkConnectionPoint);
    }
}
