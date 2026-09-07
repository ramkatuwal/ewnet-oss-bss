<?php

namespace App\Policies;

use App\Models\FiberCable;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class FiberCablePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.cables.view');
    }

    public function view(User $user, FiberCable $fiberCable): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.cables.view', $fiberCable);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fim.cables.create');
    }

    public function update(User $user, FiberCable $fiberCable): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.cables.update', $fiberCable);
    }

    public function delete(User $user, FiberCable $fiberCable): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.cables.delete', $fiberCable);
    }
}
