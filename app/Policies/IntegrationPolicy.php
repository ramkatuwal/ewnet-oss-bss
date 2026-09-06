<?php

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class IntegrationPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.view');
    }

    public function view(User $user, Integration $integration): bool
    {
        return $this->hasPermissionAndInScope($user, 'integrations.view', $integration);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermissionTo('integrations.create');
    }

    public function update(User $user, Integration $integration): bool
    {
        return $this->hasPermissionAndInScope($user, 'integrations.update', $integration);
    }

    public function delete(User $user, Integration $integration): bool
    {
        return $this->hasPermissionAndInScope($user, 'integrations.delete', $integration);
    }

    public function test(User $user, Integration $integration): bool
    {
        return $this->hasPermissionAndInScope($user, 'integrations.test', $integration);
    }

    public function sync(User $user, Integration $integration): bool
    {
        return $this->hasPermissionAndInScope($user, 'integrations.sync', $integration);
    }

    public function manageCredentials(User $user, Integration $integration): bool
    {
        return $this->hasPermissionAndInScope($user, 'integrations.credentials.manage', $integration);
    }

    public function viewLogs(User $user, Integration $integration): bool
    {
        return $this->hasPermissionAndInScope($user, 'logs.view', $integration);
    }

    /**
     * Trigger or preview an import against the integration. Permission is
     * provider-specific; tenant scope is enforced for non-global users.
     */
    public function import(User $user, Integration $integration): bool
    {
        $permission = $integration->provider === 'uisp'
            ? 'integration.uisp.import'
            : 'librenms.import';

        return $this->hasPermissionAndInScope($user, $permission, $integration);
    }
}
