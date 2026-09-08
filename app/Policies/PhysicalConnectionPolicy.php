<?php

namespace App\Policies;

use App\Models\PhysicalConnection;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class PhysicalConnectionPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $u): bool
    {
        return $u->hasPermissionTo('fim.physical-connections.view');
    }

    public function view(User $u, PhysicalConnection $c): bool
    {
        return $this->hasPermissionAndInScope($u, 'fim.physical-connections.view', $c) && $u->can('view', $c->terminationA) && $u->can('view', $c->terminationB);
    }

    public function create(User $u): bool
    {
        return $u->hasPermissionTo('fim.physical-connections.create');
    }

    public function update(User $u, PhysicalConnection $c): bool
    {
        return $this->hasPermissionAndInScope($u, 'fim.physical-connections.update', $c) && $u->can('view', $c->terminationA) && $u->can('view', $c->terminationB);
    }

    public function delete(User $u, PhysicalConnection $c): bool
    {
        return $this->hasPermissionAndInScope($u, 'fim.physical-connections.delete', $c) && $u->can('delete', $c->terminationA) && $u->can('delete', $c->terminationB);
    }
}
