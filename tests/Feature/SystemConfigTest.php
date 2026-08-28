<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class SystemConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthorized_user_cannot_access_system_config()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/system/configuration');

        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_update_system_config()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'branding' => ['app_name' => 'TEST']
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_system_config()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'branding' => ['app_name' => 'EWNET PRODUCTION']
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Configuration updated successfully.']);

        $setting = SystemSetting::where('key', 'app_name')->first();
        $this->assertNotNull($setting);
        $this->assertEquals('EWNET PRODUCTION', $setting->value);
    }

    public function test_unknown_configuration_keys_are_rejected()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'branding' => ['unknown_key' => 'test']
        ]);

        $response->assertStatus(422);
    }

    public function test_configuration_values_are_validated()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'theme' => ['compactness' => 'invalid_value']
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['theme.compactness']);
    }
}
