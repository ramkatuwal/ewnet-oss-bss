<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\SplitterProfile;
use App\Models\User;
use App\Services\ManagementScopeService;

class SplitterProfilePolicy
{
    public function viewForAsset(User $user, Asset $asset): bool
    {
        return $this->canAccessAsset($user, $asset, 'view');
    }

    public function create(User $user, Asset $asset): bool
    {
        return $this->canAccessAsset($user, $asset, 'create');
    }

    public function view(User $user, SplitterProfile $profile): bool
    {
        return $this->canAccessAsset($user, $profile->asset, 'view');
    }

    public function update(User $user, SplitterProfile $profile): bool
    {
        return $this->canAccessAsset($user, $profile->asset, 'update');
    }

    public function delete(User $user, SplitterProfile $profile): bool
    {
        return $this->canAccessAsset($user, $profile->asset, 'delete');
    }

    public function generatePorts(User $user, SplitterProfile $profile): bool
    {
        return $this->canAccessAsset($user, $profile->asset, 'generate-ports')
            && $user->hasPermissionTo('fim.passive-optical-ports.create')
            && $user->hasPermissionTo('fim.passive-optical-ports.view');
    }

    private function canAccessAsset(User $user, ?Asset $asset, string $action): bool
    {
        return $asset !== null && ! $asset->trashed()
            && $user->hasPermissionTo('fim.splitter-profiles.'.$action)
            && $user->can('view', $asset)
            && ManagementScopeService::isInScope($user, new SplitterProfile(['company_id' => $asset->company_id]));
    }
}
