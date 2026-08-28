<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class UserPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view');
    }

    public function view(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('Super Admin')) return true;
        if ($targetUser->hasRole('Super Admin')) return false;

        return $this->hasPermissionAndInScope($authUser, 'users.view', $targetUser);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.create');
    }

    public function update(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('Super Admin')) return true;
        if ($targetUser->hasRole('Super Admin')) return false;

        return $this->hasPermissionAndInScope($authUser, 'users.update', $targetUser);
    }

    public function delete(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('Super Admin')) return true;
        if ($targetUser->hasRole('Super Admin')) return false;

        return $this->hasPermissionAndInScope($authUser, 'users.delete', $targetUser);
    }
}
