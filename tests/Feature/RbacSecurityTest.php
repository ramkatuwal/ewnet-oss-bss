<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $orgAdmin;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.update', 'permissions.delete',
            'companies.view', 'companies.create', 'companies.update', 'companies.delete',
            'system.debug.view'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Create Super Admin role
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdminRole->givePermissionTo($permissions);

        // Create Organization Admin role (has role management but not Super Admin)
        $orgAdminRole = Role::firstOrCreate(['name' => 'Organization Admin']);
        $orgAdminRole->givePermissionTo([
            'users.view', 'users.create', 'users.update',
            'roles.view', 'roles.create', 'roles.update',
            'companies.view'
        ]);

        // Create users
        $this->superAdmin = User::factory()->create(['email' => 'superadmin@test.com']);
        $this->superAdmin->assignRole('Super Admin');

        $this->orgAdmin = User::factory()->create(['email' => 'orgadmin@test.com']);
        $this->orgAdmin->assignRole('Organization Admin');

        $this->regularUser = User::factory()->create(['email' => 'regular@test.com']);
    }

    // ========================================
    // UNAUTHENTICATED ACCESS TESTS
    // ========================================

    public function test_unauthenticated_user_cannot_access_roles()
    {
        $response = $this->getJson('/api/v1/security/roles');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_permissions()
    {
        $response = $this->getJson('/api/v1/security/permissions');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_users()
    {
        $response = $this->getJson('/api/v1/organization/users');
        $response->assertStatus(401);
    }

    // ========================================
    // AUTHENTICATED WITHOUT PERMISSION TESTS
    // ========================================

    public function test_user_without_permission_cannot_view_roles()
    {
        $response = $this->actingAs($this->regularUser)->getJson('/api/v1/security/roles');
        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_view_permissions()
    {
        $response = $this->actingAs($this->regularUser)->getJson('/api/v1/security/permissions');
        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_view_users()
    {
        $response = $this->actingAs($this->regularUser)->getJson('/api/v1/organization/users');
        $response->assertStatus(403);
    }

    // ========================================
    // SUPER ADMIN PROTECTION TESTS
    // ========================================

    public function test_non_super_admin_cannot_update_super_admin_role()
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        
        $response = $this->actingAs($this->orgAdmin)->putJson("/api/v1/security/roles/{$superAdminRole->id}", [
            'name' => 'Modified Super Admin'
        ]);
        
        $response->assertStatus(403);
    }

    public function test_non_super_admin_cannot_delete_super_admin_role()
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        
        $response = $this->actingAs($this->orgAdmin)->deleteJson("/api/v1/security/roles/{$superAdminRole->id}");
        
        $response->assertStatus(403);
    }

    public function test_non_super_admin_cannot_assign_super_admin_role()
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $targetUser = User::factory()->create();
        
        $response = $this->actingAs($this->orgAdmin)->putJson("/api/v1/organization/users/{$targetUser->id}", [
            'roles' => [$superAdminRole->id]
        ]);
        
        $response->assertStatus(403);
    }

    public function test_non_super_admin_cannot_create_super_admin_role()
    {
        // Delete existing Super Admin role to bypass unique validation (422)
        // so we can test the controller's custom 403 protection
        Role::where('name', 'Super Admin')->delete();
        
        $response = $this->actingAs($this->orgAdmin)->postJson('/api/v1/security/roles', [
            'name' => 'Super Admin'
        ]);
        
        $response->assertStatus(403);
    }

    public function test_non_super_admin_cannot_rename_role_to_super_admin()
    {
        // Delete existing Super Admin role to bypass unique validation (422)
        Role::where('name', 'Super Admin')->delete();
        
        $role = Role::firstOrCreate(['name' => 'Test Role To Rename']);
        
        $response = $this->actingAs($this->orgAdmin)->putJson("/api/v1/security/roles/{$role->id}", [
            'name' => 'Super Admin'
        ]);
        
        $response->assertStatus(403);
    }

    // ========================================
    // SELF-ESCALATION PREVENTION TESTS
    // ========================================

    public function test_user_cannot_escalate_themselves_to_super_admin()
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        
        $response = $this->actingAs($this->orgAdmin)->putJson("/api/v1/organization/users/{$this->orgAdmin->id}", [
            'roles' => [$superAdminRole->id]
        ]);
        
        $response->assertStatus(403);
        
        // Verify role was not changed
        $this->assertTrue($this->orgAdmin->fresh()->hasRole('Organization Admin'));
        $this->assertFalse($this->orgAdmin->fresh()->hasRole('Super Admin'));
    }

    public function test_user_cannot_assign_roles_they_dont_have()
    {
        $orgAdminRole = Role::where('name', 'Organization Admin')->first();
        
        // Regular user tries to assign Organization Admin role to themselves
        $response = $this->actingAs($this->regularUser)->putJson("/api/v1/organization/users/{$this->regularUser->id}", [
            'roles' => [$orgAdminRole->id]
        ]);
        
        $response->assertStatus(403);
    }

    // ========================================
    // PERMISSION SCOPE VALIDATION TESTS
    // ========================================

    public function test_user_cannot_assign_permissions_they_dont_have()
    {
        $debugPermission = Permission::where('name', 'system.debug.view')->first();
        
        // Org Admin doesn't have system.debug.view permission
        $response = $this->actingAs($this->orgAdmin)->postJson('/api/v1/security/roles', [
            'name' => 'Test Role',
            'permissions' => [$debugPermission->id]
        ]);
        
        $response->assertStatus(403);
    }

    public function test_only_super_admin_can_create_permissions()
    {
        $response = $this->actingAs($this->orgAdmin)->postJson('/api/v1/security/permissions', [
            'name' => 'test.permission'
        ]);
        
        $response->assertStatus(403);
    }

    public function test_only_super_admin_can_update_permissions()
    {
        $permission = Permission::first();
        
        $response = $this->actingAs($this->orgAdmin)->putJson("/api/v1/security/permissions/{$permission->id}", [
            'name' => 'modified.permission'
        ]);
        
        $response->assertStatus(403);
    }

    public function test_only_super_admin_can_delete_permissions()
    {
        $permission = Permission::first();
        
        $response = $this->actingAs($this->orgAdmin)->deleteJson("/api/v1/security/permissions/{$permission->id}");
        
        $response->assertStatus(403);
    }

    // ========================================
    // AUTHORIZED ACCESS TESTS
    // ========================================

    public function test_super_admin_can_manage_all_roles()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/security/roles');
        $response->assertStatus(200);

        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/security/roles', [
            'name' => 'Test Role'
        ]);
        $response->assertStatus(201);
    }

    public function test_super_admin_can_assign_super_admin_role()
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $targetUser = User::factory()->create();
        
        $response = $this->actingAs($this->superAdmin)->putJson("/api/v1/organization/users/{$targetUser->id}", [
            'roles' => [$superAdminRole->id]
        ]);
        
        $response->assertStatus(200);
        $this->assertTrue($targetUser->fresh()->hasRole('Super Admin'));
    }

    public function test_org_admin_can_view_roles()
    {
        $response = $this->actingAs($this->orgAdmin)->getJson('/api/v1/security/roles');
        $response->assertStatus(200);
    }

    public function test_org_admin_can_create_non_super_admin_roles()
    {
        $response = $this->actingAs($this->orgAdmin)->postJson('/api/v1/security/roles', [
            'name' => 'New Role'
        ]);
        $response->assertStatus(201);
    }

    // ========================================
    // IDOR TESTS
    // ========================================

    public function test_user_cannot_view_role_by_id_without_permission()
    {
        $role = Role::first();
        
        $response = $this->actingAs($this->regularUser)->getJson("/api/v1/security/roles/{$role->id}");
        $response->assertStatus(403);
    }

    public function test_user_cannot_update_role_by_id_without_permission()
    {
        $role = Role::first();
        
        $response = $this->actingAs($this->regularUser)->putJson("/api/v1/security/roles/{$role->id}", [
            'name' => 'Hacked Role'
        ]);
        $response->assertStatus(403);
    }

    public function test_user_cannot_delete_user_by_id_without_permission()
    {
        $targetUser = User::factory()->create();
        
        $response = $this->actingAs($this->regularUser)->deleteJson("/api/v1/organization/users/{$targetUser->id}");
        $response->assertStatus(403);
    }
}
