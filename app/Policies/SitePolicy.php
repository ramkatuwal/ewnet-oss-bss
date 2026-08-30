<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class SitePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sites.view');
    }

    public function view(User $user, Site $site): bool
    {
        return $this->hasPermissionAndInScope($user, 'sites.view', $site);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sites.create');
    }

    public function update(User $user, Site $site): bool
    {
        return $this->hasPermissionAndInScope($user, 'sites.update', $site);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->hasPermissionAndInScope($user, 'sites.delete', $site);
    }
}
