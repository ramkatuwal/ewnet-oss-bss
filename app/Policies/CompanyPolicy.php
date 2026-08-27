<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('companies.view') && 
               $this->userBelongsToCompany($user, $company);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('companies.update') && 
               $this->userBelongsToCompany($user, $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermissionTo('companies.delete') && 
               $this->userBelongsToCompany($user, $company);
    }

    protected function userBelongsToCompany(User $user, Company $company): bool
    {
        // Super Admin can access all companies
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Check if user belongs to this company through any relationship
        return $user->company_id === $company->id ||
               $user->branch?->region?->company_id === $company->id ||
               $user->department?->branch?->region?->company_id === $company->id;
    }
}
