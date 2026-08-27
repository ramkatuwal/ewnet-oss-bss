<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FimPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Base FIM permissions
            'fim.view',
            'fim.create',
            'fim.update',
            'fim.delete',

            // Site permissions
            'fim.sites.view',
            'fim.sites.create',
            'fim.sites.update',
            'fim.sites.delete',

            // Node permissions
            'fim.nodes.view',
            'fim.nodes.create',
            'fim.nodes.update',
            'fim.nodes.delete',

            // Cable permissions
            'fim.cables.view',
            'fim.cables.create',
            'fim.cables.update',
            'fim.cables.delete',

            // Topology permissions
            'fim.topology.view',
            'fim.topology.trace',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // Assign all FIM permissions to Super Admin
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo($permissions);

        // Assign limited permissions to Company Admin
        $companyAdmin = Role::firstOrCreate(['name' => 'Company Admin', 'guard_name' => 'web']);
        $companyAdmin->givePermissionTo([
            'fim.view',
            'fim.sites.view',
            'fim.sites.create',
            'fim.sites.update',
            'fim.nodes.view',
            'fim.nodes.create',
            'fim.nodes.update',
            'fim.cables.view',
            'fim.cables.create',
            'fim.cables.update',
            'fim.topology.view',
            'fim.topology.trace',
        ]);

        $this->command->info('FIM permissions seeded successfully.');
    }
}
