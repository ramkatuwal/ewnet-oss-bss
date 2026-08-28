<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SystemInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthorized_user_cannot_access_system_info()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/system/info');

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_access_system_info()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('system.info.view');

        $response = $this->actingAs($user)->getJson('/api/v1/system/info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'application' => ['name', 'environment', 'url'],
                'runtime' => ['laravel', 'php'],
                'container' => ['hostname', 'memory_limit', 'max_execution_time'],
                'services' => ['postgresql', 'redis', 'horizon', 'nginx'],
                'git' => ['commit', 'branch'],
            ]
        ]);
    }

    public function test_super_admin_can_access_system_info()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $response = $this->actingAs($user)->getJson('/api/v1/system/info');

        $response->assertStatus(200);
    }
}
