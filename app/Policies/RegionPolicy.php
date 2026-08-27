<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;

class RegionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('regions.view');
    }

    public function view(User $user, Region $region): bool
    {
        return $user->hasPermissionTo('regions.view') && 
               $this->userBelongsToRegion($user, $region);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('regions.create');
    }

    public function update(User $user, Region $region): bool
    {
        return $user->hasPermissionTo('regions.update') && 
               $this->userBelongsToRegion($user, $region);
    }

    public function delete(User $user, Region $region): bool
    {
        return $user->hasPermissionTo('regions.delete') && 
               $this->userBelongsToRegion($user, $region);
    }

    protected function userBelongsToRegion(User $user, Region $region): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->branch?->region_id === $region->id ||
               $user->department?->branch?->region_id === $region->id ||
               $user->company_id === $region->company_id;
    }
}
