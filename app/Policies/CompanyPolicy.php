<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class CompanyPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $this->hasPermissionAndInScope($user, 'companies.view', $company);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $this->hasPermissionAndInScope($user, 'companies.update', $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->hasPermissionAndInScope($user, 'companies.delete', $company);
    }
}
