<?php

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;

class IntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.view');
    }

    public function view(User $user, Integration $integration): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.create');
    }

    public function update(User $user, Integration $integration): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.update');
    }

    public function delete(User $user, Integration $integration): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.delete');
    }

    public function test(User $user, Integration $integration): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.test');
    }

    public function sync(User $user, Integration $integration): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.sync');
    }

    public function manageCredentials(User $user, Integration $integration): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.credentials.manage');
    }

    public function viewLogs(User $user, Integration $integration): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.logs.view');
    }
}
