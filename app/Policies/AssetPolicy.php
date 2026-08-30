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
        return $this->hasPermissionAndInScope($user, 'assets.view', $asset->site);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('assets.create');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->hasPermissionAndInScope($user, 'assets.update', $asset->site);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->hasPermissionAndInScope($user, 'assets.delete', $asset->site);
    }
}
