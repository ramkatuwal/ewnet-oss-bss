<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\PassiveOpticalPort;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class PassiveOpticalPortPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.passive-optical-ports.view');
    }

    public function view(User $user, PassiveOpticalPort $port): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.passive-optical-ports.view', $port)
            && $user->can('view', $port->asset)
            && $user->can('view', $port->networkConnectionPoint);
    }

    public function create(User $user, Asset $asset): bool
    {
        return $user->hasPermissionTo('fim.passive-optical-ports.create')
            && $user->can('view', $asset);
    }

    public function update(User $user, PassiveOpticalPort $port): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.passive-optical-ports.update', $port)
            && $user->can('view', $port->asset)
            && $user->can('view', $port->networkConnectionPoint);
    }

    public function delete(User $user, PassiveOpticalPort $port): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.passive-optical-ports.delete', $port)
            && $user->can('view', $port->asset)
            && $user->can('view', $port->networkConnectionPoint);
    }
}
