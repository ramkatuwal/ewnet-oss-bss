<?php

namespace App\Policies;

use App\Models\CustomerConfirmation;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class CustomerConfirmationPolicy
{
    use ChecksManagementScope;

    public function view(User $user, CustomerConfirmation $confirmation): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.confirmation.view', $confirmation);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bss.confirmation.create');
    }

    public function confirm(User $user, CustomerConfirmation $confirmation): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.confirmation.confirm', $confirmation);
    }

    public function decline(User $user, CustomerConfirmation $confirmation): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.confirmation.decline', $confirmation);
    }
}
