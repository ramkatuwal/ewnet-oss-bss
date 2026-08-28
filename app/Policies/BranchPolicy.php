<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class BranchPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->hasPermissionAndInScope($user, 'branches.view', $branch);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->hasPermissionAndInScope($user, 'branches.update', $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->hasPermissionAndInScope($user, 'branches.delete', $branch);
    }
}
