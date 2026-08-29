<?php

namespace App\Policies;

use App\Models\User;

class SystemSettingPolicy
{
    /**
     * Determine whether the user can view system configuration.
     */
    public function viewConfiguration(User $user): bool
    {
        return $user->hasPermissionTo('system.info.view');
    }

    /**
     * Determine whether the user can manage system configuration.
     */
    public function manageConfiguration(User $user): bool
    {
        return $user->hasPermissionTo('system.info.view');
    }
}
