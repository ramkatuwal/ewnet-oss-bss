<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class LeadPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bss.leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.leads.view', $lead);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bss.leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.leads.update', $lead);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.leads.convert', $lead);
    }
}
