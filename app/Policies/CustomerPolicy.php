<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class CustomerPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bss.customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.customers.view', $customer);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bss.customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.customers.update', $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.customers.retire', $customer);
    }
}
