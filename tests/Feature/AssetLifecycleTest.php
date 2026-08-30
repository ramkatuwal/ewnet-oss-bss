<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\Company;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_create_lifecycle_event()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.lifecycle.create');
        $user->givePermissionTo('assets.view');
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
            'asset_tag' => 'LIFECYCLE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-LIFE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/lifecycle", [
            'event_type' => 'RECEIVED',
            'notes' => 'Asset received for testing',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('asset_lifecycle_events', [
            'asset_id' => $asset->id,
            'event_type' => 'RECEIVED',
            'notes' => 'Asset received for testing',
        ]);
    }

    public function test_can_view_lifecycle_history()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.lifecycle.view');
        $user->givePermissionTo('assets.view');
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
            'asset_tag' => 'LIFECYCLE-002',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-LIFE-002',
            'status' => 'OPERATIONAL',
        ]);

        AssetLifecycleEvent::create([
            'asset_id' => $asset->id,
            'event_type' => 'RECEIVED',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/lifecycle");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_transfer_asset_to_another_site()
    {
        $company = Company::factory()->create();
        $site1 = Site::factory()->create(['company_id' => $company->id]);
        $site2 = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.transfer');
        $user->givePermissionTo('assets.update');
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site1->id,
            'asset_tag' => 'TRANSFER-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-TRANS-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/transfer", [
            'to_site_id' => $site2->id,
            'notes' => 'Transfer to site 2 for testing',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'site_id' => $site2->id,
        ]);
        $this->assertDatabaseHas('asset_lifecycle_events', [
            'asset_id' => $asset->id,
            'event_type' => 'TRANSFERRED',
            'from_site_id' => $site1->id,
            'to_site_id' => $site2->id,
        ]);
    }

    public function test_cannot_transfer_asset_outside_scope()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $site1 = Site::factory()->create(['company_id' => $company1->id]);
        $site2 = Site::factory()->create(['company_id' => $company2->id]);
        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('assets.transfer');
        $user->givePermissionTo('assets.update');
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
            'site_id' => $site1->id,
            'asset_tag' => 'TRANSFER-OUT-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-TRANS-OUT-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/transfer", [
            'to_site_id' => $site2->id,
            'notes' => 'Attempt transfer outside scope',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'site_id' => $site1->id,
        ]);
    }

    public function test_can_retire_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.retire');
        $user->givePermissionTo('assets.update');
        $user->givePermissionTo('assets.view');
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
            'asset_tag' => 'RETIRE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-RETIRE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/retire", [
            'notes' => 'Retiring due to end of life',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => 'RETIRED',
        ]);
        $this->assertDatabaseHas('asset_lifecycle_events', [
            'asset_id' => $asset->id,
            'event_type' => 'STATUS_CHANGED',
            'status_before' => 'OPERATIONAL',
            'status_after' => 'RETIRED',
        ]);
    }

    public function test_can_dispose_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.dispose');
        $user->givePermissionTo('assets.update');
        $user->givePermissionTo('assets.view');
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
            'asset_tag' => 'DISPOSE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-DISPOSE-001',
            'status' => 'RETIRED',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/dispose", [
            'notes' => 'Disposed due to damage',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => 'DISPOSED',
        ]);
        $this->assertDatabaseHas('asset_lifecycle_events', [
            'asset_id' => $asset->id,
            'event_type' => 'STATUS_CHANGED',
            'status_before' => 'RETIRED',
            'status_after' => 'DISPOSED',
        ]);
    }

    public function test_cannot_dispose_non_retired_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.dispose');
        $user->givePermissionTo('assets.update');
        $user->givePermissionTo('assets.view');
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
            'asset_tag' => 'DISPOSE-FAIL-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-DISPOSE-FAIL-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/dispose", [
            'notes' => 'Attempt to dispose operational asset',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => 'OPERATIONAL',
        ]);
    }
}
