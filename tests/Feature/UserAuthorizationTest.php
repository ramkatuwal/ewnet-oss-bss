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

class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $companyAdmin;
    protected $branchAdmin;
    protected $deptAdmin;
    protected $regularUser;
    protected $companyA;
    protected $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
        ];
        foreach ($permissions as $perm) Permission::firstOrCreate(['name' => $perm]);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdminRole->givePermissionTo($permissions);

        $companyAdminRole = Role::firstOrCreate(['name' => 'Company Admin']);
        $companyAdminRole->givePermissionTo(['users.view', 'users.create', 'users.update', 'users.delete']);

        $branchAdminRole = Role::firstOrCreate(['name' => 'Branch Admin']);
        $branchAdminRole->givePermissionTo(['users.view', 'users.create', 'users.update', 'users.delete']);

        $deptAdminRole = Role::firstOrCreate(['name' => 'Department Admin']);
        $deptAdminRole->givePermissionTo(['users.view', 'users.create', 'users.update', 'users.delete']);

        // Companies
        $this->companyA = Company::factory()->create(['name' => 'Company A']);
        $this->companyB = Company::factory()->create(['name' => 'Company B']);

        // Hierarchy for Company A
        $regionA = Region::factory()->create(['company_id' => $this->companyA->id]);
        $branchA = Branch::factory()->create(['region_id' => $regionA->id]);
        $deptA = Department::factory()->create(['branch_id' => $branchA->id]);

        // Users
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->companyAdmin = User::factory()->create(['company_id' => $this->companyA->id]);
        $this->companyAdmin->assignRole('Company Admin');

        $this->branchAdmin = User::factory()->create(['company_id' => $this->companyA->id, 'branch_id' => $branchA->id]);
        $this->branchAdmin->assignRole('Branch Admin');

        $this->deptAdmin = User::factory()->create(['company_id' => $this->companyA->id, 'branch_id' => $branchA->id, 'department_id' => $deptA->id]);
        $this->deptAdmin->assignRole('Department Admin');

        $this->regularUser = User::factory()->create(['company_id' => $this->companyB->id]);
    }

    // --- CRITICAL: Cross-Company Isolation ---
    public function test_company_admin_cannot_list_another_companys_users()
    {
        User::factory()->count(3)->create(['company_id' => $this->companyB->id]);
        
        $response = $this->actingAs($this->companyAdmin)->getJson('/api/v1/organization/users');
        $response->assertStatus(200);
        
        $userIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($this->regularUser->id, $userIds);
    }

    public function test_company_admin_cannot_view_another_companys_user()
    {
        $response = $this->actingAs($this->companyAdmin)->getJson("/api/v1/organization/users/{$this->regularUser->id}");
        $response->assertStatus(403);
    }

    public function test_company_admin_cannot_update_another_companys_user()
    {
        $response = $this->actingAs($this->companyAdmin)->putJson("/api/v1/organization/users/{$this->regularUser->id}", [
            'name' => 'Hacked Name'
        ]);
        $response->assertStatus(403);
    }

    public function test_company_admin_cannot_delete_another_companys_user()
    {
        $response = $this->actingAs($this->companyAdmin)->deleteJson("/api/v1/organization/users/{$this->regularUser->id}");
        $response->assertStatus(403);
    }

    // --- CRITICAL: Super Admin Protection ---
    public function test_company_admin_cannot_modify_super_admin()
    {
        $response = $this->actingAs($this->companyAdmin)->putJson("/api/v1/organization/users/{$this->superAdmin->id}", [
            'name' => 'Hacked Super Admin'
        ]);
        $response->assertStatus(403);
    }

    public function test_company_admin_cannot_delete_super_admin()
    {
        $response = $this->actingAs($this->companyAdmin)->deleteJson("/api/v1/organization/users/{$this->superAdmin->id}");
        $response->assertStatus(403);
    }

    // --- HIGH: Hierarchical Scope Enforcement ---
    public function test_branch_admin_cannot_create_user_outside_permitted_branch_scope()
    {
        $otherBranch = Branch::factory()->create(['region_id' => Region::factory()->create(['company_id' => $this->companyA->id])->id]);
        
        $response = $this->actingAs($this->branchAdmin)->postJson('/api/v1/organization/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'company_id' => $this->companyA->id,
            'branch_id' => $otherBranch->id, // Attempting to escape scope
        ]);
        $response->assertStatus(403);
    }

    public function test_department_admin_cannot_escape_department_scope()
    {
        $otherDept = Department::factory()->create(['branch_id' => $this->branchAdmin->branch_id]);
        
        $response = $this->actingAs($this->deptAdmin)->postJson('/api/v1/organization/users', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'password123',
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchAdmin->branch_id,
            'department_id' => $otherDept->id, // Attempting to escape scope
        ]);
        $response->assertStatus(403);
    }

    public function test_client_cannot_manipulate_company_id_to_create_user_in_another_company()
    {
        $response = $this->actingAs($this->companyAdmin)->postJson('/api/v1/organization/users', [
            'name' => 'Malicious User',
            'email' => 'malicious@example.com',
            'password' => 'password123',
            'company_id' => $this->companyB->id, // Manipulation attempt
        ]);
        $response->assertStatus(403); // Blocked by UserRequest authorize()
    }

    // --- Role Assignment Security ---
    public function test_client_cannot_use_role_assignment_to_escalate_privileges()
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $targetUser = User::factory()->create(['company_id' => $this->companyA->id]);
        
        $response = $this->actingAs($this->companyAdmin)->putJson("/api/v1/organization/users/{$targetUser->id}", [
            'roles' => [$superAdminRole->id]
        ]);
        $response->assertStatus(403);
    }

    // --- Positive Cases ---
    public function test_super_admin_retains_global_access()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/organization/users');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(5, count($response->json('data')));
    }

    public function test_legitimate_company_scoped_operations_continue_working()
    {
        $response = $this->actingAs($this->companyAdmin)->postJson('/api/v1/organization/users', [
            'name' => 'Valid User',
            'email' => 'valid@example.com',
            'password' => 'password123',
            'company_id' => $this->companyA->id,
        ]);
        $response->assertStatus(201);
        $this->assertEquals($this->companyA->id, $response->json('company_id'));
    }

    public function test_legitimate_branch_scoped_operations_continue_working()
    {
        $response = $this->actingAs($this->branchAdmin)->postJson('/api/v1/organization/users', [
            'name' => 'Valid Branch User',
            'email' => 'branchvalid@example.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(201);
        $this->assertEquals($this->branchAdmin->branch_id, $response->json('branch_id'));
    }
}
