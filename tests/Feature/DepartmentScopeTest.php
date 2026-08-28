<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Region $regionWest;
    protected Branch $branchSurkhet;
    protected Branch $branchDailekh;
    protected Department $deptTech;
    protected Department $deptSales;
    protected User $superAdmin;
    protected User $companyManager;
    protected User $regionManager;
    protected User $branchManager;
    protected User $deptManager;

    protected function setUp(): void
    {
        parent::setUp();

        $perms = ['departments.view', 'departments.create', 'departments.update', 'departments.delete'];
        foreach ($perms as $p) Permission::firstOrCreate(['name' => $p]);

        $managerRole = Role::firstOrCreate(['name' => 'DeptManager']);
        $managerRole->syncPermissions($perms);

        $superRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superRole->syncPermissions($perms);

        $this->companyA = Company::factory()->create(['name' => 'Company A']);
        $this->companyB = Company::factory()->create(['name' => 'Company B']);
        $this->regionWest = Region::factory()->create(['company_id' => $this->companyA->id]);
        $this->branchSurkhet = Branch::factory()->create(['region_id' => $this->regionWest->id]);
        $this->branchDailekh = Branch::factory()->create(['region_id' => $this->regionWest->id]);
        $this->deptTech = Department::factory()->create([
            'branch_id' => $this->branchSurkhet->id,
            'company_id' => $this->companyA->id,
        ]);
        $this->deptSales = Department::factory()->create([
            'branch_id' => $this->branchSurkhet->id,
            'company_id' => $this->companyA->id,
        ]);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->companyManager = User::factory()->create(['company_id' => $this->companyA->id]);
        $this->companyManager->assignRole('DeptManager');
        UserManagementScope::create([
            'user_id' => $this->companyManager->id,
            'scope_type' => 'company',
            'scope_id' => $this->companyA->id,
        ]);

        $this->regionManager = User::factory()->create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchSurkhet->id,
        ]);
        $this->regionManager->assignRole('DeptManager');
        UserManagementScope::create([
            'user_id' => $this->regionManager->id,
            'scope_type' => 'region',
            'scope_id' => $this->regionWest->id,
        ]);

        $this->branchManager = User::factory()->create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchSurkhet->id,
        ]);
        $this->branchManager->assignRole('DeptManager');
        UserManagementScope::create([
            'user_id' => $this->branchManager->id,
            'scope_type' => 'branch',
            'scope_id' => $this->branchSurkhet->id,
        ]);

        $this->deptManager = User::factory()->create([
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchSurkhet->id,
            'department_id' => $this->deptTech->id,
        ]);
        $this->deptManager->assignRole('DeptManager');
        UserManagementScope::create([
            'user_id' => $this->deptManager->id,
            'scope_type' => 'department',
            'scope_id' => $this->deptTech->id,
        ]);
    }

    // ── SUPER ADMIN ────────────────────────────────────────────

    public function test_super_admin_can_view_all_departments(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/organization/departments');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    // ── COMPANY SCOPE ──────────────────────────────────────────

    public function test_company_scope_can_view_own_company_departments(): void
    {
        $response = $this->actingAs($this->companyManager)->getJson('/api/v1/organization/departments');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->deptTech->id, $ids);
        $this->assertContains($this->deptSales->id, $ids);
    }

    public function test_company_scope_cannot_view_other_company_departments(): void
    {
        $otherDept = Department::factory()->create([
            'branch_id' => Branch::factory()->create([
                'region_id' => Region::factory()->create(['company_id' => $this->companyB->id])->id,
            ])->id,
            'company_id' => $this->companyB->id,
        ]);

        $response = $this->actingAs($this->companyManager)->getJson('/api/v1/organization/departments');
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($otherDept->id, $ids);
    }

    // ── REGION SCOPE ───────────────────────────────────────────

    public function test_region_scope_can_view_own_region_departments(): void
    {
        $response = $this->actingAs($this->regionManager)->getJson('/api/v1/organization/departments');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->deptTech->id, $ids);
    }

    // ── BRANCH SCOPE ───────────────────────────────────────────

    public function test_branch_scope_can_view_own_branch_departments(): void
    {
        $response = $this->actingAs($this->branchManager)->getJson('/api/v1/organization/departments');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->deptTech->id, $ids);
        $this->assertContains($this->deptSales->id, $ids);
    }

    public function test_branch_scope_cannot_view_other_branch_departments(): void
    {
        $otherDept = Department::factory()->create([
            'branch_id' => $this->branchDailekh->id,
            'company_id' => $this->companyA->id,
        ]);

        $response = $this->actingAs($this->branchManager)->getJson('/api/v1/organization/departments');
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($otherDept->id, $ids);
    }

    // ── DEPARTMENT SCOPE ───────────────────────────────────────

    public function test_department_scope_can_view_own_department(): void
    {
        $response = $this->actingAs($this->deptManager)
            ->getJson("/api/v1/organization/departments/{$this->deptTech->id}");
        $response->assertStatus(200);
        $this->assertEquals($this->deptTech->id, $response->json('data.id'));
    }

    public function test_department_scope_cannot_view_other_department(): void
    {
        $response = $this->actingAs($this->deptManager)
            ->getJson("/api/v1/organization/departments/{$this->deptSales->id}");
        $response->assertStatus(403);
    }

    // ── PARENT BRANCH PROTECTION ───────────────────────────────

    public function test_branch_manager_cannot_move_department_to_other_branch(): void
    {
        $response = $this->actingAs($this->branchManager)
            ->putJson("/api/v1/organization/departments/{$this->deptTech->id}", [
                'name' => $this->deptTech->name,
                'code' => $this->deptTech->code,
                'branch_id' => $this->branchDailekh->id,
                'company_id' => $this->companyA->id,
            ]);
        $response->assertStatus(403);
    }

    public function test_company_manager_can_create_department_in_any_branch_within_company(): void
    {
        $response = $this->actingAs($this->companyManager)
            ->postJson('/api/v1/organization/departments', [
                'name' => 'New Dept',
                'code' => 'ND001',
                'branch_id' => $this->branchDailekh->id,
                'company_id' => $this->companyA->id,
            ]);
        $response->assertStatus(201);
    }

    // ── DELETE PROTECTION ──────────────────────────────────────

    public function test_cannot_delete_department_with_assigned_users(): void
    {
        User::factory()->create(['department_id' => $this->deptTech->id]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/organization/departments/{$this->deptTech->id}");
        $response->assertStatus(422);
    }

    // ── RESOURCE COMPLETENESS ──────────────────────────────────

    public function test_department_resource_includes_hierarchy_and_user_count(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/organization/departments/{$this->deptTech->id}");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('company_id', $data);
        $this->assertArrayHasKey('branch', $data);
        $this->assertArrayHasKey('user_count', $data);
        $this->assertArrayHasKey('region', $data['branch']);
    }
}
