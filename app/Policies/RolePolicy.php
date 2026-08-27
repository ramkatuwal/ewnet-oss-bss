<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    public function create(User $user): bool
    {
        if (!$user->hasPermissionTo('roles.create')) {
            return false;
        }
        
        // Note: Name validation happens in controller, but we check permission here
        return true;
    }

    public function update(User $user, Role $role): bool
    {
        // CRITICAL: Prevent non-Super Admins from modifying Super Admin role
        if ($role->name === 'Super Admin' && !$user->hasRole('Super Admin')) {
            return false;
        }
        
        return $user->hasPermissionTo('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        // CRITICAL: Prevent deletion of Super Admin role
        if ($role->name === 'Super Admin') {
            return false;
        }
        
        return $user->hasPermissionTo('roles.delete');
    }

    /**
     * Determine if user can assign this role to another user
     */
    public function assign(User $user, Role $role): bool
    {
        // Only Super Admin can assign Super Admin role
        if ($role->name === 'Super Admin') {
            return $user->hasRole('Super Admin');
        }
        
        // Users can only assign roles if they have the role assignment permission
        return $user->hasPermissionTo('users.update');
    }
}
