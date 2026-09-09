<?php

namespace App\Policies;

use App\Models\NetworkPort;
use App\Models\PonDomain;
use App\Models\User;
use App\Services\ManagementScopeService;

class PonDomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.pon-domains.view');
    }

    public function view(User $user, PonDomain $domain): bool
    {
        return $this->viewAny($user)
            && ManagementScopeService::isInScope($user, $domain)
            && $domain->oltPort !== null
            && $user->can('view', $domain->oltPort);
    }

    public function create(User $user, NetworkPort $port): bool
    {
        return $user->hasPermissionTo('net.pon-domains.create')
            && $user->can('view', $port);
    }

    public function delete(User $user, PonDomain $domain): bool
    {
        return $user->hasPermissionTo('net.pon-domains.delete')
            && ManagementScopeService::isInScope($user, $domain)
            && $domain->oltPort !== null
            && $user->can('view', $domain->oltPort);
    }
}
