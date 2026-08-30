<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SystemPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'system.info.view',
            'system.config.view',
            'system.config.manage',
            'integrations.view',
            'integrations.create',
            'integrations.update',
            'integrations.delete',
            'integrations.test',
            'integrations.sync',
            'integrations.credentials.manage',
            'integrations.logs.view',
            'sites.view',
            'sites.create',
            'sites.update',
            'sites.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign to Super Admin
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }
    }
}
