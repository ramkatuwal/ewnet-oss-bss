<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\PonDomain;
use App\Models\PonMembership;
use App\Models\User;
use App\Services\ManagementScopeService;

class PonMembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.pon-memberships.view');
    }

    public function view(User $user, PonMembership $membership): bool
    {
        return $this->viewAny($user)
            && ManagementScopeService::isInScope($user, $membership)
            && $membership->ponDomain !== null
            && $user->can('view', $membership->ponDomain)
            && $membership->onuAsset !== null
            && $user->can('view', $membership->onuAsset);
    }

    public function create(User $user, PonDomain $domain, Asset $onuAsset): bool
    {
        return $user->hasPermissionTo('net.pon-memberships.create')
            && $user->can('view', $domain)
            && $user->can('view', $onuAsset);
    }

    public function delete(User $user, PonMembership $membership): bool
    {
        return $user->hasPermissionTo('net.pon-memberships.delete')
            && ManagementScopeService::isInScope($user, $membership)
            && $membership->ponDomain !== null
            && $user->can('view', $membership->ponDomain);
    }
}
