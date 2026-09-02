<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UispPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permission
        $permission = Permission::firstOrCreate([
            'name' => 'integration.uisp.import',
            'guard_name' => 'web',
        ]);

        // Assign to Super Admin role
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        if (!$superAdmin->hasPermissionTo($permission)) {
            $superAdmin->givePermissionTo($permission);
        }

        // Assign to Admin role if it exists
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        if (!$admin->hasPermissionTo($permission)) {
            $admin->givePermissionTo($permission);
        }

        $this->command->info('UISP Import permission created and assigned to Super Admin and Admin roles.');
    }
}
