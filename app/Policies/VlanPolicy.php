<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vlan;
use App\Policies\Concerns\ChecksManagementScope;

class VlanPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.vlans.view');
    }

    public function view(User $user, Vlan $vlan): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.vlans.view', $vlan);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('net.vlans.create');
    }

    public function update(User $user, Vlan $vlan): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.vlans.update', $vlan);
    }

    public function delete(User $user, Vlan $vlan): bool
    {
        return $this->hasPermissionAndInScope($user, 'net.vlans.delete', $vlan);
    }
}
