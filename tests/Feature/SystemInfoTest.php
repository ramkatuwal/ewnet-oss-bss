<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SystemInfoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $this->admin->assignRole($role);
        }
    }

    public function test_system_info_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/system/info');
        $response->assertStatus(401);
    }

    public function test_system_info_requires_permission(): void
    {
        $user = User::factory()->create();
        $nocRole = Role::where('name', 'NOC')->first();
        if ($nocRole) {
            $user->assignRole($nocRole);
        }

        // Revoke system.info.view if they have it
        $perm = Permission::where('name', 'system.info.view')->first();
        if ($perm && $user->hasPermissionTo($perm)) {
            $user->revokePermissionTo($perm);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/system/info');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_system_info(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/system/info');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'application' => ['name', 'environment', 'url'],
                    'runtime' => ['laravel', 'php'],
                    'container',
                    'services' => ['postgresql', 'redis', 'horizon', 'nginx'],
                    'git' => ['commit', 'branch'],
                ]
            ]);
    }

    public function test_system_info_returns_correct_git_data(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/system/info');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('commit', $data['git']);
        $this->assertArrayHasKey('branch', $data['git']);
        
        // Commit should be 7-character hash or null
        if ($data['git']['commit']) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{7}$/', $data['git']['commit']);
        }
    }

    public function test_system_info_logs_audit_event(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/system/info')
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.info.viewed',
            'result' => 'success',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_configuration_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/system/configuration');
        $response->assertStatus(401);
    }

    public function test_admin_can_get_configuration(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/system/configuration');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'branding' => ['app_name', 'browser_title'],
                    'header' => ['show_logo', 'show_title'],
                    'theme' => ['compactness', 'dark_mode', 'primary_color'],
                ]
            ]);
    }

    public function test_admin_can_update_configuration(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/system/configuration', [
                'theme' => [
                    'dark_mode' => true,
                    'primary_color' => '#ff5722',
                ],
                'header' => [
                    'show_logo' => false,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'updated']);
    }

    public function test_update_configuration_validates_input(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/system/configuration', [
                'theme' => [
                    'primary_color' => 'invalid-hex', // Invalid format
                    'compactness' => 'invalid-value', // Invalid enum
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['theme.primary_color', 'theme.compactness']);
    }

    public function test_update_configuration_logs_audit_event(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/v1/system/configuration', [
                'theme' => [
                    'dark_mode' => true,
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.configuration.update',
            'result' => 'success',
            'actor_id' => $this->admin->id,
        ]);
    }
}
