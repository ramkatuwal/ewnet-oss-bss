<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IntegrationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'integrations.view',
            'integrations.create',
            'integrations.update',
            'integrations.delete',
            'integrations.test',
            'integrations.sync',
            'integrations.credentials.manage',
            'integrations.logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }
    }
}
