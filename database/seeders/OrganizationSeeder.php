<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update Company
        $company = Company::updateOrCreate(
            ['name' => 'Everest Wireless Network Pvt. Ltd.'],
            [
                'registration_number' => '123456789',
                'pan_number' => '1234567890',
                'email' => 'info@ewnet.com.np',
                'phone' => '+977-1-1234567',
                'address' => 'Kathmandu, Nepal',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'country' => 'Nepal',
                'is_active' => true,
            ]
        );

        // Create Regions with unique codes
        $region1 = Region::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'KTM-VALLEY'],
            [
                'name' => 'Kathmandu Valley Region',
                'description' => 'Central region covering Kathmandu Valley',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'country' => 'Nepal',
                'is_active' => true,
            ]
        );

        $region2 = Region::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'POKHARA'],
            [
                'name' => 'Pokhara Region',
                'description' => 'Western region covering Pokhara',
                'city' => 'Pokhara',
                'state' => 'Gandaki',
                'country' => 'Nepal',
                'is_active' => true,
            ]
        );

        // Create Branches with unique codes
        $branch1 = Branch::updateOrCreate(
            ['region_id' => $region1->id, 'code' => 'KTM-HQ'],
            [
                'name' => 'Kathmandu Headquarters',
                'address' => 'Baluwatar, Kathmandu',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'country' => 'Nepal',
                'phone' => '+977-1-1234567',
                'email' => 'hq@ewnet.com.np',
                'is_active' => true,
            ]
        );

        // Create Departments
        Department::updateOrCreate(
            ['branch_id' => $branch1->id, 'code' => 'NOC'],
            [
                'company_id' => $company->id,
                'name' => 'Network Operations Center',
                'description' => 'Core NOC team',
                'is_active' => true,
            ]
        );

        // Create Admin User
        User::updateOrCreate(
            ['email' => 'admin@ewnet.com.np'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Ram@2admin111'),
            ]
        );

        $this->command->info('✅ Organization seeded successfully!');
        $this->command->info("Company: {$company->name}");
        $this->command->info("Admin user: admin@ewnet.com.np");
    }
}
