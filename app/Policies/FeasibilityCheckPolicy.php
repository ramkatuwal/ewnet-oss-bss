<?php

namespace App\Policies;

use App\Models\FeasibilityCheck;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class FeasibilityCheckPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('bss.feasibility.view');
    }

    public function view(User $user, FeasibilityCheck $feasibilityCheck): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.feasibility.view', $feasibilityCheck);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('bss.feasibility.create');
    }

    public function update(User $user, FeasibilityCheck $feasibilityCheck): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.feasibility.update', $feasibilityCheck);
    }

    public function assign(User $user, FeasibilityCheck $feasibilityCheck): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.feasibility.assign', $feasibilityCheck);
    }

    public function decide(User $user, FeasibilityCheck $feasibilityCheck): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.feasibility.decide', $feasibilityCheck);
    }

    public function survey(User $user, FeasibilityCheck $feasibilityCheck): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.feasibility.survey.update', $feasibilityCheck);
    }

    public function evidence(User $user, FeasibilityCheck $feasibilityCheck): bool
    {
        return $this->hasPermissionAndInScope($user, 'bss.feasibility.evidence.create', $feasibilityCheck);
    }

    public function createConfirmation(User $user, FeasibilityCheck $feasibilityCheck): bool
    {
        return $user->hasPermissionTo('bss.confirmation.create')
            && $this->isInScope($user, $feasibilityCheck);
    }
}
