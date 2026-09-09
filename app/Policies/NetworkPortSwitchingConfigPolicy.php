<?php

namespace App\Policies;

use App\Models\NetworkPort;
use App\Models\NetworkPortSwitchingConfig;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class NetworkPortSwitchingConfigPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.port-switching-configs.view');
    }

    public function view(User $user, NetworkPortSwitchingConfig $configuration): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.port-switching-configs.view', $configuration)
            && $user->can('view', $configuration->networkPort);
    }

    public function configure(User $user, NetworkPort $port): bool
    {
        return $user->hasPermissionTo('net.port-switching-configs.configure') && $user->can('view', $port);
    }

    public function delete(User $user, NetworkPortSwitchingConfig $configuration): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.port-switching-configs.delete', $configuration)
            && $user->can('view', $configuration->networkPort);
    }
}
