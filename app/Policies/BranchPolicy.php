<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->hasPermissionTo('branches.view') && 
               $this->userBelongsToBranch($user, $branch);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasPermissionTo('branches.update') && 
               $this->userBelongsToBranch($user, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->hasPermissionTo('branches.delete') && 
               $this->userBelongsToBranch($user, $branch);
    }

    protected function userBelongsToBranch(User $user, Branch $branch): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->branch_id === $branch->id ||
               $user->department?->branch_id === $branch->id;
    }
}
