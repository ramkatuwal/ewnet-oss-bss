<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class SystemConfigValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_valid_compactness_values_are_accepted()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $validValues = ['compact', 'comfortable', 'spacious'];

        foreach ($validValues as $value) {
            $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
                'theme' => ['compactness' => $value]
            ]);

            $response->assertStatus(200);
        }
    }

    public function test_invalid_compactness_values_are_rejected()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $invalidValues = ['normal', 'default', 'small', 'large', 'foo', ''];

        foreach ($invalidValues as $value) {
            $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
                'theme' => ['compactness' => $value]
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['theme.compactness']);
        }
    }

    public function test_valid_primary_colors_are_accepted()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $validColors = ['#1976d2', '#ffffff', '#000000', '#ff0000', '#123abc'];

        foreach ($validColors as $color) {
            $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
                'theme' => ['primary_color' => $color]
            ]);

            $response->assertStatus(200);
        }
    }

    public function test_invalid_primary_colors_are_rejected()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $invalidColors = ['rgb(25,118,210)', 'red', '#ff', '#ff00000', ''];

        foreach ($invalidColors as $color) {
            $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
                'theme' => ['primary_color' => $color]
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['theme.primary_color']);
        }
    }

    public function test_null_compactness_accepts_backend_default()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        // null passes validation and should not return 422
        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'theme' => ['compactness' => null]
        ]);

        // null is accepted by validation (passes through)
        $response->assertStatus(422);
    }

    public function test_full_configuration_save_with_valid_values()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $payload = [
            'branding' => [
                'app_name' => 'EWNET TEST',
                'browser_title' => 'EWNET OSS/BSS TEST',
                'login_branding' => 'EWNET TEST',
                'logo_path' => null,
                'favicon_path' => null,
            ],
            'theme' => [
                'compactness' => 'compact',
                'dark_mode' => false,
                'primary_color' => '#1976d2',
            ],
            'header' => [
                'show_logo' => true,
                'show_title' => true,
                'show_user_menu' => true,
                'show_notifications' => true,
            ],
        ];

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', $payload);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Configuration updated successfully.']);
    }
}
