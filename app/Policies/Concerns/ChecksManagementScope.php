<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\ManagementScopeService;
use Illuminate\Database\Eloquent\Model;

trait ChecksManagementScope
{
    protected function hasPermissionAndInScope(User $user, string $permission, Model $resource): bool
    {
        return $user->hasPermissionTo($permission)
            && ManagementScopeService::isInScope($user, $resource);
    }

    protected function isInScope(User $user, Model $resource): bool
    {
        return ManagementScopeService::isInScope($user, $resource);
    }
}
