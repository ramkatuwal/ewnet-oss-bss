<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DebugRouteTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        Permission::firstOrCreate(['name' => 'system.debug.view']);
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $role->givePermissionTo('system.debug.view');

        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');
    }

    public function test_authenticated_with_permission_can_access_debug()
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/debug/status');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_debug()
    {
        $response = $this->getJson('/api/v1/debug/status');
        $response->assertStatus(401);
    }

    public function test_user_without_permission_cannot_access_debug()
    {
        $userWithoutPermission = User::factory()->create();
        $response = $this->actingAs($userWithoutPermission)->getJson('/api/v1/debug/status');
        $response->assertStatus(403);
    }
}
