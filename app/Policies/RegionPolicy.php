<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class RegionPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('regions.view');
    }

    public function view(User $user, Region $region): bool
    {
        return $this->hasPermissionAndInScope($user, 'regions.view', $region);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('regions.create');
    }

    public function update(User $user, Region $region): bool
    {
        return $this->hasPermissionAndInScope($user, 'regions.update', $region);
    }

    public function delete(User $user, Region $region): bool
    {
        return $this->hasPermissionAndInScope($user, 'regions.delete', $region);
    }
}
