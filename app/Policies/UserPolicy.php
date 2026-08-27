<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.view');
    }

    public function view(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('Super Admin')) {
            return true;
        }

        // Protect Super Admin accounts from non-Super Admins
        if ($targetUser->hasRole('Super Admin')) {
            return false;
        }

        return $this->userCanManageTarget($authUser, $targetUser);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.create');
    }

    public function update(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('Super Admin')) {
            return true;
        }

        // Protect Super Admin accounts from non-Super Admins
        if ($targetUser->hasRole('Super Admin')) {
            return false;
        }

        return $this->userCanManageTarget($authUser, $targetUser);
    }

    public function delete(User $authUser, User $targetUser): bool
    {
        if ($authUser->hasRole('Super Admin')) {
            return true;
        }

        // Protect Super Admin accounts from non-Super Admins
        if ($targetUser->hasRole('Super Admin')) {
            return false;
        }

        return $this->userCanManageTarget($authUser, $targetUser);
    }

    /**
     * Determine if the authenticated user has hierarchical authority over the target user.
     */
    protected function userCanManageTarget(User $authUser, User $targetUser): bool
    {
        // Must be in the same company
        if ($authUser->company_id !== $targetUser->company_id) {
            return false;
        }

        // Company-level administrator (has company_id, but no branch_id)
        if ($authUser->company_id && !$authUser->branch_id) {
            return true;
        }

        // Branch-level administrator (has branch_id, but no department_id)
        if ($authUser->branch_id && !$authUser->department_id) {
            return $authUser->branch_id === $targetUser->branch_id;
        }

        // Department-level administrator (has department_id)
        if ($authUser->department_id) {
            return $authUser->department_id === $targetUser->department_id || $authUser->id === $targetUser->id;
        }

        return false;
    }
}
