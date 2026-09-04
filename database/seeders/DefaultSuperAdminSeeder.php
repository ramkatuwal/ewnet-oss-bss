<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DefaultSuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure Super Admin role exists
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Check if admin user already exists
        if (!User::where('email', 'admin@ewnet.com.np')->exists()) {
            $user = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@ewnet.com.np',
                'password' => Hash::make('Admin@2026!'), // Default password - must be changed on first login
                'email_verified_at' => now(),
            ]);

            $user->assignRole($role);

            // Sync all permissions to Super Admin role
            $allPermissions = Permission::all();
            if ($allPermissions->isNotEmpty()) {
                $role->syncPermissions($allPermissions);
                $this->command->info('Super Admin role synced with ' . $allPermissions->count() . ' permissions.');
            }

            $this->command->info('Default Super Admin user created successfully.');
            $this->command->warn('Please change the default password immediately after first login.');
        } else {
            $this->command->info('Super Admin user already exists. Skipping creation.');
        }
    }
}
