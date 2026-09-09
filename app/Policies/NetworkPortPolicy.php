<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\NetworkPort;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class NetworkPortPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.network-ports.view');
    }

    public function view(User $user, NetworkPort $port): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.network-ports.view', $port)
            && $user->can('view', $port->asset);
    }

    public function create(User $user, Asset $asset): bool
    {
        return $user->hasPermissionTo('net.network-ports.create')
            && $user->can('view', $asset);
    }

    public function update(User $user, NetworkPort $port): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.network-ports.update', $port)
            && $user->can('view', $port->asset);
    }

    public function delete(User $user, NetworkPort $port): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.network-ports.delete', $port)
            && $user->can('view', $port->asset);
    }
}
