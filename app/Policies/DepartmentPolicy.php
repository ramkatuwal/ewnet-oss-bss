<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.view') && 
               $this->userBelongsToDepartment($user, $department);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.update') && 
               $this->userBelongsToDepartment($user, $department);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.delete') && 
               $this->userBelongsToDepartment($user, $department);
    }

    protected function userBelongsToDepartment(User $user, Department $department): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->department_id === $department->id;
    }
}
