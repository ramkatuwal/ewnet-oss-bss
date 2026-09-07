<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DefaultSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure Super Admin role exists
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Create or update user
        $user = User::updateOrCreate(
            ['email' => 'admin@ewnet.com.np'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@2026!'),
                'email_verified_at' => now(),
            ]
        );

        // Assign role to user
        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        // Get ALL permissions currently in the database
        $allPermissions = Permission::all();

        // Sync all permissions to the Super Admin role
        $role->syncPermissions($allPermissions);

        // Clear permission cache to ensure immediate availability
        PermissionRegistrar::class;
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('Super Admin role synced with '.$allPermissions->count().' permissions.');
        $this->command->warn('Default Password: Admin@2026!');
    }
}
