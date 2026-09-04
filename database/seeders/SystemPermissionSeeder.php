<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SystemPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'system.info.view',
            'system.config.view',
            'system.config.manage',
            'system.debug.view',
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            'regions.view',
            'regions.create',
            'regions.update',
            'regions.delete',
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',
            'designations.view',
            'designations.create',
            'designations.update',
            'designations.delete',
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',
            'assets.view',
            'assets.create',
            'assets.update',
            'assets.delete',
            'assets.import',
            'assets.export',
            'assets.lifecycle.view',
            'assets.lifecycle.create',
            'assets.transfer',
            'assets.retire',
            'assets.dispose',
            'sites.view',
            'sites.create',
            'sites.update',
            'sites.delete',
            'sites.import',
            'sites.export',
            'integrations.view',
            'integrations.create',
            'integrations.update',
            'integrations.delete',
            'integrations.sync',
            'integrations.credentials.manage',
            'integrations.test',
            'integrations.logs.view',
            'librenms.import',
            'integration.uisp.import',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'audit.view',
            'logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $this->command->info('System permissions seeded successfully.');
    }
}

            // Import UI access
            'imports.view',
