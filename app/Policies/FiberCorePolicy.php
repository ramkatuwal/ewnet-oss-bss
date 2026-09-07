<?php

namespace App\Policies;

use App\Models\FiberCore;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class FiberCorePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.fiber-cores.view')
            && $user->hasPermissionTo('fim.fiber-segments.view');
    }

    public function view(User $user, FiberCore $core): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-cores.view', $core)
            && $user->can('view', $core->fiberSegment);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fim.fiber-cores.create');
    }

    public function update(User $user, FiberCore $core): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-cores.update', $core)
            && $user->can('update', $core->fiberSegment);
    }

    public function delete(User $user, FiberCore $core): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-cores.delete', $core)
            && $user->can('delete', $core->fiberSegment);
    }
}
