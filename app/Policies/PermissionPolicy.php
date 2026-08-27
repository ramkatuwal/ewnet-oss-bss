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
        // Only Super Admin can create new permissions
        return $user->hasRole('Super Admin');
    }

    public function update(User $user, Permission $permission): bool
    {
        // Only Super Admin can modify permissions
        return $user->hasRole('Super Admin');
    }

    public function delete(User $user, Permission $permission): bool
    {
        // Only Super Admin can delete permissions
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine if user can assign this permission to a role
     */
    public function assign(User $user, Permission $permission): bool
    {
        // Users can only assign permissions they themselves have
        return $user->hasPermissionTo($permission->name);
    }
}
