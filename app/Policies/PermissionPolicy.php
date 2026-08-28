<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('permissions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function assign(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo($permission->name);
    }
}
