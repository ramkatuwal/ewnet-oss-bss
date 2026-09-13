<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDeviceType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetModelSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminUser(): User
    {
        $user = User::where('email', 'admin@ewnet.com.np')->first();
        $user->givePermissionTo('system.config.manage');

        return $user;
    }

    public function test_can_list_categories()
    {
        Sanctum::actingAs($this->adminUser());
        $response = $this->getJson('/api/v1/settings/model-settings/asset/categories');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'code', 'name', 'is_active']]]);
        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    public function test_can_create_category()
    {
        Sanctum::actingAs($this->adminUser());
        $response = $this->postJson('/api/v1/settings/model-settings/asset/categories', [
            'code' => 'WIRELESS',
            'name' => 'Wireless',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'WIRELESS');
        $this->assertDatabaseHas('asset_categories', ['code' => 'WIRELESS']);
    }

    public function test_can_update_category()
    {
        Sanctum::actingAs($this->adminUser());
        $cat = AssetCategory::where('code', 'POWER')->first();

        $response = $this->putJson("/api/v1/settings/model-settings/asset/categories/{$cat->id}", [
            'name' => 'Power Systems',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('asset_categories', ['id' => $cat->id, 'name' => 'Power Systems']);
    }

    public function test_management_permission_is_required_for_all_settings_operations(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $category = AssetCategory::firstOrFail();

        $this->getJson('/api/v1/settings/model-settings/asset/categories')->assertForbidden();
        $this->deleteJson("/api/v1/settings/model-settings/asset/categories/{$category->id}")->assertForbidden();
    }

    public function test_device_type_updates_preserve_category_consistency_and_active_selection(): void
    {
        Sanctum::actingAs($this->adminUser());
        $network = AssetCategory::where('code', 'NETWORK')->firstOrFail();
        $power = AssetCategory::where('code', 'POWER')->firstOrFail();
        $router = AssetDeviceType::where('category_id', $network->id)->where('code', 'ROUTER')->firstOrFail();
        $ups = AssetDeviceType::where('category_id', $power->id)->where('code', 'UPS')->firstOrFail();

        $this->putJson("/api/v1/settings/model-settings/asset/device-types/{$router->id}", [
            'category_id' => $power->id,
            'code' => 'UPS',
        ])->assertUnprocessable();

        $ups->update(['is_active' => false]);
        $this->postJson('/api/v1/settings/model-settings/asset/device-types', [
            'category_id' => $power->id,
            'code' => 'INACTIVE_NEW',
            'name' => 'Inactive New',
            'is_active' => false,
        ])->assertCreated();
    }

    public function test_can_delete_category_without_assets()
    {
        Sanctum::actingAs($this->adminUser());
        $cat = AssetCategory::where('code', 'OTHER')->first();

        $response = $this->deleteJson("/api/v1/settings/model-settings/asset/categories/{$cat->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('asset_categories', ['id' => $cat->id]);
    }

    public function test_can_list_device_types()
    {
        Sanctum::actingAs($this->adminUser());
        $response = $this->getJson('/api/v1/settings/model-settings/asset/device-types');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(10, count($response->json('data')));
    }

    public function test_can_filter_device_types_by_category()
    {
        Sanctum::actingAs($this->adminUser());
        $cat = AssetCategory::where('code', 'NETWORK')->first();

        $response = $this->getJson("/api/v1/settings/model-settings/asset/device-types?category_id={$cat->id}");

        $response->assertOk();
        foreach ($response->json('data') as $dt) {
            $this->assertEquals($cat->id, $dt['category_id']);
        }
    }

    public function test_can_create_device_type()
    {
        Sanctum::actingAs($this->adminUser());
        $cat = AssetCategory::where('code', 'NETWORK')->first();

        $response = $this->postJson('/api/v1/settings/model-settings/asset/device-types', [
            'code' => 'FIREWALL',
            'name' => 'Firewall',
            'category_id' => $cat->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('asset_device_types', ['code' => 'FIREWALL']);
    }

    public function test_can_list_units()
    {
        Sanctum::actingAs($this->adminUser());
        $response = $this->getJson('/api/v1/settings/model-settings/asset/units');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(8, count($response->json('data')));
    }

    public function test_can_create_unit()
    {
        Sanctum::actingAs($this->adminUser());
        $response = $this->postJson('/api/v1/settings/model-settings/asset/units', [
            'code' => 'liter',
            'name' => 'Liter',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('asset_units', ['code' => 'liter']);
    }

    public function test_cannot_delete_category_with_assets()
    {
        Sanctum::actingAs($this->adminUser());
        $cat = AssetCategory::where('code', 'POWER')->first();

        // Create an asset referencing this category to simulate dependency
        Asset::create([
            'site_id' => Site::factory()->create()->id,
            'asset_tag' => 'AST-TEST-001',
            'category' => 'POWER',
            'type' => 'BATTERY',
            'quantity' => 1,
            'serial_number' => 'SN-DEL-TEST',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->deleteJson("/api/v1/settings/model-settings/asset/categories/{$cat->id}");

        $response->assertStatus(409);
    }
}
