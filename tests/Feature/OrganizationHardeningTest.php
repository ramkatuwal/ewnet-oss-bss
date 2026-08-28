<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $companyUser;
    protected $company;
    protected $otherCompany;
    protected $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create permissions
        $this->permissions = [
            'companies.view', 'companies.create', 'companies.update', 'companies.delete',
            'regions.view', 'regions.create', 'regions.update', 'regions.delete',
            'branches.view', 'branches.create', 'branches.update', 'branches.delete',
            'departments.view', 'departments.create', 'departments.update', 'departments.delete',
        ];

        foreach ($this->permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 2. Create Super Admin role and assign permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdminRole->givePermissionTo($this->permissions);

        // 3. Create Super Admin user
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        // 4. Create two companies
        $this->company = Company::factory()->create(['name' => 'Company A']);
        $this->otherCompany = Company::factory()->create(['name' => 'Company B']);

        // 5. Create hierarchy for Company A
        $region = Region::factory()->create(['company_id' => $this->company->id, 'name' => 'Base Region', 'code' => 'BR001']);
        $branch = Branch::factory()->create(['region_id' => $region->id, 'name' => 'Base Branch', 'code' => 'BB001']);
        $department = Department::factory()->create(['branch_id' => $branch->id, 'name' => 'Base Dept', 'code' => 'BD001']);
        
        // 6. Create user belonging to Company A's hierarchy
        $this->companyUser = User::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $this->companyUser->givePermissionTo($this->permissions);
    }

    public function test_duplicate_region_name_in_same_company_rejected()
    {
        Region::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'North Region',
            'code' => 'NR001',
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/organization/regions', [
            'name' => 'North Region',
            'code' => 'NR002',
            'company_id' => $this->company->id,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_same_region_name_allowed_in_different_companies()
    {
        Region::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'North Region',
            'code' => 'NR001',
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/organization/regions', [
            'name' => 'North Region',
            'code' => 'NR002',
            'company_id' => $this->otherCompany->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_user_cannot_view_region_from_another_company()
    {
        $otherRegion = Region::factory()->create(['company_id' => $this->otherCompany->id, 'name' => 'Other', 'code' => 'OT001']);

        $response = $this->actingAs($this->companyUser)->getJson("/api/v1/organization/regions/{$otherRegion->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_region_from_another_company()
    {
        $otherRegion = Region::factory()->create(['company_id' => $this->otherCompany->id, 'name' => 'Other', 'code' => 'OT001']);

        $response = $this->actingAs($this->companyUser)->putJson("/api/v1/organization/regions/{$otherRegion->id}", [
            'name' => 'Hacked Region',
            'code' => 'HR001',
            'company_id' => $this->otherCompany->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_cannot_create_region_in_another_company()
    {
        $response = $this->actingAs($this->companyUser)->postJson('/api/v1/organization/regions', [
            'name' => 'Malicious Region',
            'code' => 'MR001',
            'company_id' => $this->otherCompany->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_only_sees_regions_from_own_company()
    {
        $ownRegion = Region::factory()->create(['company_id' => $this->company->id, 'name' => 'Own Region', 'code' => 'OR001']);
        $otherRegion = Region::factory()->create(['company_id' => $this->otherCompany->id, 'name' => 'Other Region', 'code' => 'OT002']);

        $response = $this->actingAs($this->companyUser)->getJson('/api/v1/organization/regions?per_page=100');

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $regionIds = array_column($responseData, 'id');
        $companyIds = array_column($responseData, 'company_id');
        
        // Department-scoped user should NOT see ANY regions (no upward access)
        $this->assertNotContains($ownRegion->id, $regionIds, 'Department-scoped user should NOT see regions in own company');
        $this->assertNotContains($otherRegion->id, $regionIds, 'User should NOT see other company region');
        $this->assertEmpty($regionIds, 'Department-scoped user should see no regions at all');
    }

    public function test_super_admin_sees_all_regions()
    {
        $region1 = Region::factory()->create(['company_id' => $this->company->id, 'name' => 'Admin Region 1', 'code' => 'AR001']);
        $region2 = Region::factory()->create(['company_id' => $this->otherCompany->id, 'name' => 'Admin Region 2', 'code' => 'AR002']);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/organization/regions?per_page=100');

        $response->assertStatus(200);
        
        $responseData = $response->json('data');
        $regionIds = array_column($responseData, 'id');
        $companyIds = array_column($responseData, 'company_id');
        
        $this->assertContains($region1->id, $regionIds, 'Super Admin should see region 1');
        $this->assertContains($region2->id, $regionIds, 'Super Admin should see region 2');
        $this->assertContains($this->company->id, $companyIds, 'Super Admin should see company A regions');
        $this->assertContains($this->otherCompany->id, $companyIds, 'Super Admin should see company B regions');
    }

    public function test_user_cannot_access_branch_from_another_region()
    {
        $otherRegion = Region::factory()->create(['company_id' => $this->otherCompany->id, 'name' => 'Other', 'code' => 'OT003']);
        $otherBranch = Branch::factory()->create(['region_id' => $otherRegion->id, 'name' => 'Other Branch', 'code' => 'OB001']);

        $response = $this->actingAs($this->companyUser)->getJson("/api/v1/organization/branches/{$otherBranch->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_access_department_from_another_branch()
    {
        $otherBranch = Branch::factory()->create(['region_id' => $this->companyUser->branch->region_id, 'name' => 'Other Branch', 'code' => 'OB002']);
        $otherDepartment = Department::factory()->create(['branch_id' => $otherBranch->id, 'name' => 'Other Dept', 'code' => 'OD001']);

        $response = $this->actingAs($this->companyUser)->getJson("/api/v1/organization/departments/{$otherDepartment->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_companies()
    {
        $response = $this->getJson('/api/v1/organization/companies');

        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_create_company()
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)->postJson('/api/v1/organization/companies', [
            'name' => 'Test Company',
        ]);

        $response->assertStatus(403);
    }
}
