<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class ServicePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bss.services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.services.view', $service);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bss.services.create');
    }

    public function update(User $user, Service $service): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.services.update', $service);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.services.retire', $service);
    }
}
