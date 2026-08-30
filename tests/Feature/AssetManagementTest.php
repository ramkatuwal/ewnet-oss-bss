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
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('assets', ['asset_tag' => 'EW-TEST-001']);
    }

    public function test_cannot_create_asset_without_permission()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/assets', []);
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
            'quantity' => 1,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_view_assets_within_scope()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['site_id' => $site->id]);
        
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->getJson('/api/v1/assets');
        $response->assertStatus(200);
        $response->assertJsonFragment(['asset_tag' => $asset->asset_tag]);
    }

    public function test_cannot_view_assets_outside_scope()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        $site1 = Site::factory()->create(['company_id' => $company1->id]);
        $site2 = Site::factory()->create(['company_id' => $company2->id]);
        
        Asset::factory()->create(['site_id' => $site1->id, 'asset_tag' => 'VISIBLE-001']);
        Asset::factory()->create(['site_id' => $site2->id, 'asset_tag' => 'HIDDEN-001']);
        
        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('assets.view');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->getJson('/api/v1/assets');
        $response->assertStatus(200);
        $response->assertJsonMissing(['asset_tag' => 'HIDDEN-001']);
    }

    public function test_can_export_assets()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        Asset::factory()->count(3)->create(['site_id' => $site->id]);
        
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.export');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->get('/api/v1/assets/export?format=csv');
        $response->assertStatus(200);
    }

    public function test_dashboard_returns_correct_totals()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        
        // 2 records, 5 units total
        Asset::factory()->create(['site_id' => $site->id, 'quantity' => 2, 'status' => 'OPERATIONAL']);
        Asset::factory()->create(['site_id' => $site->id, 'quantity' => 3, 'status' => 'MAINTENANCE']);
        
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->getJson('/api/v1/assets/dashboard');
        $response->assertStatus(200);
        $response->assertJsonPath('data.total_records', 2);
        $response->assertJsonPath('data.total_units', 5);
        $response->assertJsonPath('data.sites_with_assets', 1);
    }
}
