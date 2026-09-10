<?php

namespace App\Policies;

use App\Models\CustomerService;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class CustomerServicePolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bss.customer-services.view');
    }

    public function view(User $user, CustomerService $customerService): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.customer-services.view', $customerService);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bss.customer-services.create');
    }

    public function update(User $user, CustomerService $customerService): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.customer-services.update', $customerService);
    }

    public function delete(User $user, CustomerService $customerService): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.customer-services.retire', $customerService);
    }
}
