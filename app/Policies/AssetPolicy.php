<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class AssetPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.view', $site);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('assets.create');
    }

    public function update(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.update', $site);
    }

    public function delete(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.delete', $site);
    }

    // Lifecycle permissions
    public function viewLifecycle(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.lifecycle.view', $site);
    }

    public function createLifecycle(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.lifecycle.create', $site);
    }

    public function transfer(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.transfer', $site);
    }

    public function retire(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.retire', $site);
    }

    public function dispose(User $user, Asset $asset): bool
    {
        $site = $asset->site()->first();
        if (!$site) return false;
        return $this->hasPermissionAndInScope($user, 'assets.dispose', $site);
    }
}
