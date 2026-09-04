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
            // Dashboard
            'dashboard.view',

            // System Info & Config
            'system.info.view',
            'system.config.view',
            'system.config.manage',
            'system.debug.view',

            // Assets
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

            // Sites
            'sites.view',
            'sites.create',
            'sites.update',
            'sites.delete',
            'sites.import',
            'sites.export',

            // Integrations
            'integrations.view',
            'integrations.create',
            'integrations.update',
            'integrations.delete',
            'integrations.sync',

            // Users & Roles
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',

            // Audit & Logs
            'audit.view',
            'logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign all permissions to Super Admin role
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $this->command->info('System permissions seeded successfully.');
    }
}
