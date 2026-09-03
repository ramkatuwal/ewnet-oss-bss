<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Region;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagementScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Region $regionWest;
    protected Region $regionCentral;
    protected Branch $branchSurkhet;
    protected Branch $branchDailekh;
    protected Department $deptTech;
    protected Department $deptSales;
    protected User $superAdmin;
    protected User $companyManager;
    protected User $regionManager;
    protected User $branchManager;
    protected User $deptManager;
    protected User $unscopedUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset Faker unique history to prevent collisions
        \Faker\Factory::create()->unique(true);

        // Create permissions
        $perms = [
            'companies.view', 'companies.update',
            'regions.view', 'regions.update',
            'branches.view', 'branches.update',
            'departments.view', 'departments.update',
            'users.view', 'users.update', 'users.create',
        ];
        foreach ($perms as $p) Permission::firstOrCreate(['name' => $p]);

        // Create roles
        $managerRole = Role::firstOrCreate(['name' => 'Manager']);
        $managerRole->syncPermissions($perms);

        $superRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superRole->syncPermissions($perms);

        // Create org hierarchy
        $this->companyA = Company::factory()->create(['name' => 'Company A']);
        $this->companyB = Company::factory()->create(['name' => 'Company B']);
        $this->regionWest = Region::factory()->create(['company_id' => $this->companyA->id, 'name' => 'West']);
        $this->regionCentral = Region::factory()->create(['company_id' => $this->companyA->id, 'name' => 'Central']);
        $this->branchSurkhet = Branch::factory()->create(['region_id' => $this->regionWest->id, 'name' => 'Surkhet']);
        $this->branchDailekh = Branch::factory()->create(['region_id' => $this->regionWest->id, 'name' => 'Dailekh']);
        $this->deptTech = Department::factory()->create(['branch_id' => $this->branchSurkhet->id, 'company_id' => $this->companyA->id, 'name' => 'Tech']);
        $this->deptSales = Department::factory()->create(['branch_id' => $this->branchSurkhet->id, 'company_id' => $this->companyA->id, 'name' => 'Sales']);

        // Create users with membership
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->companyManager = User::factory()->create(['company_id' => $this->companyA->id]);
        $this->companyManager->assignRole('Manager');
        UserManagementScope::create([
            'user_id' => $this->companyManager->id,
            'scope_type' => 'company',
            'scope_id' => $this->companyA->id,
        ]);

        $this->regionManager = User::factory()->create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchSurkhet->id]);
        $this->regionManager->assignRole('Manager');
        UserManagementScope::create([
            'user_id' => $this->regionManager->id,
            'scope_type' => 'region',
            'scope_id' => $this->regionWest->id,
        ]);

        $this->branchManager = User::factory()->create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchSurkhet->id]);
        $this->branchManager->assignRole('Manager');
        UserManagementScope::create([
            'user_id' => $this->branchManager->id,
            'scope_type' => 'branch',
            'scope_id' => $this->branchSurkhet->id,
        ]);

        $this->deptManager = User::factory()->create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchSurkhet->id, 'department_id' => $this->deptTech->id]);
        $this->deptManager->assignRole('Manager');
        UserManagementScope::create([
            'user_id' => $this->deptManager->id,
            'scope_type' => 'department',
            'scope_id' => $this->deptTech->id,
        ]);

        // User with NO explicit scope (fallback test)
        $this->unscopedUser = User::factory()->create(['company_id' => $this->companyA->id, 'branch_id' => $this->branchSurkhet->id]);
        $this->unscopedUser->assignRole('Manager');
    }

    // ── SUPER ADMIN TESTS ─────────────────────────────────────

    public function test_super_admin_has_global_scope(): void
    {
        $scopes = ManagementScopeService::getEffectiveScopes($this->superAdmin);
        $this->assertCount(1, $scopes);
        $this->assertEquals('global', $scopes[0]['scope_type']);
    }

    public function test_super_admin_can_access_any_company(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->superAdmin, $this->companyA));
        $this->assertTrue(ManagementScopeService::isInScope($this->superAdmin, $this->companyB));
    }

    public function test_super_admin_can_access_any_resource(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->superAdmin, $this->regionWest));
        $this->assertTrue(ManagementScopeService::isInScope($this->superAdmin, $this->branchSurkhet));
        $this->assertTrue(ManagementScopeService::isInScope($this->superAdmin, $this->deptTech));
    }

    // ── COMPANY SCOPE TESTS ───────────────────────────────────

    public function test_company_scope_allows_own_company(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->companyManager, $this->companyA));
    }

    public function test_company_scope_denies_other_company(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->companyManager, $this->companyB));
    }

    public function test_company_scope_includes_child_regions(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->companyManager, $this->regionWest));
        $this->assertTrue(ManagementScopeService::isInScope($this->companyManager, $this->regionCentral));
    }

    public function test_company_scope_includes_child_branches(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->companyManager, $this->branchSurkhet));
        $this->assertTrue(ManagementScopeService::isInScope($this->companyManager, $this->branchDailekh));
    }

    public function test_company_scope_includes_child_departments(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->companyManager, $this->deptTech));
        $this->assertTrue(ManagementScopeService::isInScope($this->companyManager, $this->deptSales));
    }

    // ── REGION SCOPE TESTS ────────────────────────────────────

    public function test_region_scope_allows_own_region(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->regionManager, $this->regionWest));
    }

    public function test_region_scope_denies_other_region(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->regionManager, $this->regionCentral));
    }

    public function test_region_scope_denies_parent_company(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->regionManager, $this->companyA));
    }

    public function test_region_scope_includes_child_branches(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->regionManager, $this->branchSurkhet));
        $this->assertTrue(ManagementScopeService::isInScope($this->regionManager, $this->branchDailekh));
    }

    public function test_region_scope_includes_child_departments(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->regionManager, $this->deptTech));
    }

    // ── BRANCH SCOPE TESTS ────────────────────────────────────

    public function test_branch_scope_allows_own_branch(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->branchManager, $this->branchSurkhet));
    }

    public function test_branch_scope_denies_other_branch(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->branchManager, $this->branchDailekh));
    }

    public function test_branch_scope_denies_parent_region(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->branchManager, $this->regionWest));
    }

    public function test_branch_scope_denies_parent_company(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->branchManager, $this->companyA));
    }

    public function test_branch_scope_includes_child_departments(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->branchManager, $this->deptTech));
        $this->assertTrue(ManagementScopeService::isInScope($this->branchManager, $this->deptSales));
    }

    // ── DEPARTMENT SCOPE TESTS ────────────────────────────────

    public function test_department_scope_allows_own_department(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->deptManager, $this->deptTech));
    }

    public function test_department_scope_denies_other_department(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->deptManager, $this->deptSales));
    }

    public function test_department_scope_denies_parent_branch(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->deptManager, $this->branchSurkhet));
    }

    public function test_department_scope_denies_parent_region(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->deptManager, $this->regionWest));
    }

    public function test_department_scope_denies_parent_company(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->deptManager, $this->companyA));
    }

    // ── FALLBACK MEMBERSHIP TESTS ─────────────────────────────

    public function test_fallback_derives_scope_from_membership(): void
    {
        $scopes = ManagementScopeService::getEffectiveScopes($this->unscopedUser);
        $this->assertCount(1, $scopes);
        // Should fallback to branch (most specific membership)
        $this->assertEquals('branch', $scopes[0]['scope_type']);
        $this->assertEquals($this->branchSurkhet->id, $scopes[0]['scope_id']);
    }

    public function test_fallback_user_can_access_own_branch_resources(): void
    {
        $this->assertTrue(ManagementScopeService::isInScope($this->unscopedUser, $this->branchSurkhet));
        $this->assertTrue(ManagementScopeService::isInScope($this->unscopedUser, $this->deptTech));
    }

    public function test_fallback_user_cannot_access_other_branch(): void
    {
        $this->assertFalse(ManagementScopeService::isInScope($this->unscopedUser, $this->branchDailekh));
    }

    // ── MULTIPLE SCOPE TESTS ──────────────────────────────────

    public function test_multiple_scopes_allow_access_to_both(): void
    {
        // Give region manager also Central region scope
        UserManagementScope::create([
            'user_id' => $this->regionManager->id,
            'scope_type' => 'region',
            'scope_id' => $this->regionCentral->id,
        ]);

        // Reload
        $this->regionManager->load('managementScopes');

        $this->assertTrue(ManagementScopeService::isInScope($this->regionManager, $this->regionWest));
        $this->assertTrue(ManagementScopeService::isInScope($this->regionManager, $this->regionCentral));
    }

    // ── SCOPE ASSIGNMENT SECURITY TESTS ───────────────────────

    public function test_company_manager_can_grant_branch_scope_within_company(): void
    {
        $this->assertTrue(
            ManagementScopeService::canGrantScope($this->companyManager, 'branch', $this->branchSurkhet->id)
        );
    }

    public function test_branch_manager_cannot_grant_company_scope(): void
    {
        $this->assertFalse(
            ManagementScopeService::canGrantScope($this->branchManager, 'company', $this->companyA->id)
        );
    }

    public function test_branch_manager_cannot_grant_scope_outside_branch(): void
    {
        $this->assertFalse(
            ManagementScopeService::canGrantScope($this->branchManager, 'branch', $this->branchDailekh->id)
        );
    }

    public function test_super_admin_can_grant_any_scope(): void
    {
        $this->assertTrue(ManagementScopeService::canGrantScope($this->superAdmin, 'company', $this->companyA->id));
        $this->assertTrue(ManagementScopeService::canGrantScope($this->superAdmin, 'region', $this->regionWest->id));
        $this->assertTrue(ManagementScopeService::canGrantScope($this->superAdmin, 'branch', $this->branchSurkhet->id));
        $this->assertTrue(ManagementScopeService::canGrantScope($this->superAdmin, 'department', $this->deptTech->id));
    }

    // ── DUPLICATE PREVENTION TEST ─────────────────────────────

    public function test_duplicate_scope_assignment_is_prevented(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        UserManagementScope::create([
            'user_id' => $this->companyManager->id,
            'scope_type' => 'company',
            'scope_id' => $this->companyA->id,
        ]);
    }

    // ── CROSS-COMPANY ISOLATION TEST ──────────────────────────

    public function test_company_a_manager_cannot_access_company_b_resources(): void
    {
        $companyBRegion = Region::factory()->create(['company_id' => $this->companyB->id]);
        $this->assertFalse(ManagementScopeService::isInScope($this->companyManager, $companyBRegion));
    }

    // ── API SCOPE ASSIGNMENT TEST ─────────────────────────────

    public function test_api_scope_assignment_works(): void
    {
        $targetUser = User::factory()->create(['company_id' => $this->companyA->id]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/organization/users/{$targetUser->id}/management-scopes", [
                'scope_type' => 'company',
                'scope_id' => $this->companyA->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('user_management_scopes', [
            'user_id' => $targetUser->id,
            'scope_type' => 'company',
            'scope_id' => $this->companyA->id,
        ]);
    }

    public function test_api_scope_revocation_works(): void
    {
        // Use the existing scope from setUp instead of creating a duplicate
        $existing = UserManagementScope::where('user_id', $this->companyManager->id)
            ->where('scope_type', 'company')
            ->first();

        $this->assertNotNull($existing, 'Company manager should have a company scope from setUp');

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/organization/users/{$this->companyManager->id}/management-scopes/{$existing->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('user_management_scopes', ['id' => $existing->id]);
    }

    public function test_non_super_admin_cannot_grant_outside_scope(): void
    {
        $targetUser = User::factory()->create(['company_id' => $this->companyA->id]);

        $response = $this->actingAs($this->branchManager)
            ->postJson("/api/v1/organization/users/{$targetUser->id}/management-scopes", [
                'scope_type' => 'company',
                'scope_id' => $this->companyA->id,
            ]);

        $response->assertStatus(403);
    }
}
