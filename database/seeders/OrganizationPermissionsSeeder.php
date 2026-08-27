<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OrganizationPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Companies
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            // Regions
            'regions.view',
            'regions.create',
            'regions.update',
            'regions.delete',
            // Branches
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            // Departments
            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',
            // Designations
            'designations.view',
            'designations.create',
            'designations.update',
            'designations.delete',
            // Employees
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            // Roles
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            // Permissions
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo($permissions);

        $this->command->info('Organization permissions seeded successfully.');
    }
}
