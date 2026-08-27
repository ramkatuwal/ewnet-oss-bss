<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacApiTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $permissions = [
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.update', 'permissions.delete',
            'users.view', 'users.create', 'users.update', 'users.delete'
        ];
        
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }
        
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $role->givePermissionTo($permissions);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');
    }

    public function test_authenticated_can_access_roles()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/security/roles');
        $response->assertStatus(200);
    }

    public function test_authenticated_can_access_permissions()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/security/permissions');
        $response->assertStatus(200);
    }

    public function test_authenticated_can_access_users()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/organization/users');
        $response->assertStatus(200);
    }
}
