<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Core System & Organization Permissions (Includes Assets, Sites, Integrations)
        $this->call(SystemPermissionSeeder::class);
        $this->call(OrganizationPermissionsSeeder::class);
        $this->call(IntegrationPermissionSeeder::class);
        $this->call(UispPermissionSeeder::class);

        // 2. Create Default Super Admin and assign ALL existing permissions
        $this->call(DefaultSuperAdminSeeder::class);
    }
}
