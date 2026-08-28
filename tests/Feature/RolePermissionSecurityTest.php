<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $perms = ['roles.view','roles.create','roles.update','roles.delete',
                   'permissions.view','permissions.create','permissions.update','permissions.delete',
                   'users.view','users.update','companies.view'];
        foreach ($perms as $p) Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);

        $superRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superRole->syncPermissions(Permission::all());

        $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $managerRole->syncPermissions(['roles.view','roles.create','roles.update','users.view','companies.view']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('Manager');
    }

    // ── ROLE CRUD ──────────────────────────────────────────────

    public function test_super_admin_can_list_roles(): void
    {
        $this->actingAs($this->superAdmin)->getJson('/api/v1/security/roles')->assertStatus(200);
    }

    public function test_manager_can_list_roles(): void
    {
        $this->actingAs($this->manager)->getJson('/api/v1/security/roles')->assertStatus(200);
    }

    public function test_super_admin_can_create_role(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/security/roles', [
            'name' => 'TestRole', 'permissions' => [],
        ])->assertStatus(201);
    }

    public function test_manager_cannot_assign_permissions_they_dont_have(): void
    {
        $deletePerm = Permission::where('name', 'roles.delete')->first();
        $this->actingAs($this->manager)->postJson('/api/v1/security/roles', [
            'name' => 'EscalatedRole', 'permissions' => [$deletePerm->id],
        ])->assertStatus(403);
    }

    public function test_non_super_admin_cannot_create_super_admin_role(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/security/roles', [
            'name' => 'Super Admin',
        ])->assertStatus(422);
    }

    public function test_non_super_admin_cannot_delete_super_admin_role(): void
    {
        $sa = Role::where('name', 'Super Admin')->first();
        $this->actingAs($this->manager)->deleteJson("/api/v1/security/roles/{$sa->id}")->assertStatus(403);
    }

    public function test_non_super_admin_cannot_update_super_admin_role(): void
    {
        $sa = Role::where('name', 'Super Admin')->first();
        $this->actingAs($this->manager)->putJson("/api/v1/security/roles/{$sa->id}", [
            'name' => 'HackedAdmin',
        ])->assertStatus(403);
    }

    // ── PERMISSION CRUD ────────────────────────────────────────

    public function test_super_admin_can_create_permission(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/security/permissions', [
            'name' => 'test.new_perm',
        ])->assertStatus(201);
    }

    public function test_non_super_admin_cannot_create_permission(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/security/permissions', [
            'name' => 'test.escalated',
        ])->assertStatus(403);
    }

    public function test_non_super_admin_cannot_update_permission(): void
    {
        $perm = Permission::where('name', 'companies.view')->first();
        $this->actingAs($this->manager)->putJson("/api/v1/security/permissions/{$perm->id}", [
            'name' => 'companies.hacked',
        ])->assertStatus(403);
    }

    public function test_non_super_admin_cannot_delete_permission(): void
    {
        $perm = Permission::where('name', 'companies.view')->first();
        $this->actingAs($this->manager)->deleteJson("/api/v1/security/permissions/{$perm->id}")->assertStatus(403);
    }

    // ── ROLE USERS ENDPOINT ────────────────────────────────────

    public function test_role_users_endpoint_returns_assigned_users(): void
    {
        $role = Role::where('name', 'Manager')->first();
        $response = $this->actingAs($this->superAdmin)->getJson("/api/v1/security/roles/{$role->id}/users");
        $response->assertStatus(200);
    }

    // ── RESOURCE FORMAT ────────────────────────────────────────

    public function test_role_resource_includes_required_fields(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/security/roles');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('is_protected', $first);
        $this->assertArrayHasKey('permission_count', $first);
        $this->assertArrayHasKey('user_count', $first);
    }

    public function test_permission_resource_includes_domain(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/security/permissions');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $first = $data[0];
        $this->assertArrayHasKey('domain', $first);
        $this->assertArrayHasKey('action', $first);
    }
}
