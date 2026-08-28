<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class SystemPolicy
{
    use ChecksManagementScope;

    public function viewInfo(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('system.info.view');
    }

    public function viewConfiguration(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('system.config.view');
    }

    public function manageConfiguration(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermissionTo('system.config.manage');
    }
}
