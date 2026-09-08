<?php

namespace App\Policies;

use App\Models\SplitterBranch;
use App\Models\SplitterProfile;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;
use App\Services\ManagementScopeService;

class SplitterBranchPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.splitter-branches.view');
    }

    public function view(User $user, SplitterBranch $branch): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.splitter-branches.view', $branch)
            && $user->can('view', $branch->splitterProfile)
            && $user->can('view', $branch->inputPort)
            && $user->can('view', $branch->outputPort);
    }

    public function create(User $user, SplitterProfile $profile): bool
    {
        return $user->hasPermissionTo('fim.splitter-branches.create')
            && $user->can('view', $profile)
            && ManagementScopeService::isInScope($user, new SplitterBranch([
                'company_id' => $profile->company_id,
            ]));
    }

    public function delete(User $user, SplitterBranch $branch): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.splitter-branches.delete', $branch)
            && $user->can('view', $branch->splitterProfile);
    }
}
