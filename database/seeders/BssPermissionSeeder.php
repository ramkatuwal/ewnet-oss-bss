<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BssPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = ['bss.customers.view', 'bss.customers.create', 'bss.customers.update', 'bss.customers.retire', 'bss.services.view', 'bss.services.create', 'bss.services.update', 'bss.services.retire', 'bss.customer-services.view', 'bss.customer-services.create', 'bss.customer-services.update', 'bss.customer-services.retire', 'bss.leads.view', 'bss.leads.create', 'bss.leads.update', 'bss.leads.convert'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Only roles that already exist receive conservative read access; operational grants remain explicit.
        foreach (['Viewer'] as $name) {
            if ($role = Role::where('name', $name)->where('guard_name', 'web')->first()) {
                $role->givePermissionTo(['bss.customers.view', 'bss.services.view', 'bss.customer-services.view']);
            }
        }
        $admin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());
    }
}
