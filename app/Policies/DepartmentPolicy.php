<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class DepartmentPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->hasPermissionAndInScope($user, 'departments.view', $department);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->hasPermissionAndInScope($user, 'departments.update', $department);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->hasPermissionAndInScope($user, 'departments.delete', $department);
    }
}
