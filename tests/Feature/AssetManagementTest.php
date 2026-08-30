<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_create_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'EW-TEST-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-TEST-001',
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('assets', ['asset_tag' => 'EW-TEST-001']);
    }

    public function test_cannot_create_asset_without_permission()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        // User does NOT have assets.create permission
        $user->givePermissionTo('sites.view');

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'TEST',
            'category' => 'OTHER',
            'type' => 'Test',
            'quantity' => 1,
            'status' => 'OPERATIONAL',
        ]);
        $response->assertStatus(403);
    }

    public function test_cannot_create_asset_with_duplicate_tag()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        Asset::factory()->create(['asset_tag' => 'EW-DUP-001']);

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'EW-DUP-001',
            'category' => 'NETWORK',
            'type' => 'Router',
            'quantity' => 2,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['asset_tag']);
    }

    public function test_can_update_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.update');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'asset_tag' => 'EW-UPDATE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-UPDATE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/assets/{$asset->id}", [
            'status' => 'MAINTENANCE',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'MAINTENANCE']);
    }

    public function test_can_delete_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.delete');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'asset_tag' => 'EW-DELETE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-DELETE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_cannot_access_asset_outside_scope()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $site1 = Site::factory()->create(['company_id' => $company1->id]);
        $site2 = Site::factory()->create(['company_id' => $company2->id]);

        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site2->id,
            'asset_tag' => 'EW-OUTSIDE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-OUTSIDE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(403);
    }

    public function test_can_view_assets_list()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        Asset::factory()->count(3)->create([
            'site_id' => $site->id,
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => function () {
                return 'SN-LIST-' . fake()->unique()->numberBetween(100, 999);
            },
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/assets');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_cannot_create_asset_serial_required_for_power_quantity_1()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'EW-SERIAL-REQ-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['serial_number']);
    }

    public function test_can_create_asset_serial_not_required_for_power_quantity_gt_1()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'EW-SERIAL-NOT-REQ-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 5,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('assets', ['asset_tag' => 'EW-SERIAL-NOT-REQ-001']);
    }

    public function test_cannot_create_asset_serial_required_for_network_quantity_1()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'EW-SERIAL-REQ-NET-001',
            'category' => 'NETWORK',
            'type' => 'Router',
            'quantity' => 1,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['serial_number']);
    }
}
