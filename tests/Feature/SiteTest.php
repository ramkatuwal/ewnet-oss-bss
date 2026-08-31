<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Site;
use App\Models\Asset;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_create_site_with_minimal_fields()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'KTM-POP-001',
            'name' => 'Kathmandu Core POP',
            'type' => 'pop',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sites', ['site_code' => 'KTM-POP-001']);
    }

    public function test_site_code_must_be_unique()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('sites.create');

        Site::create([
            'site_code' => 'KTM-POP-001',
            'name' => 'Existing Site',
            'type' => 'pop',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'KTM-POP-001',
            'name' => 'Duplicate Site',
            'type' => 'pop',
            'status' => 'active',
        ]);

        $response->assertStatus(422);
    }

    public function test_site_can_have_optional_gps()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'PKR-POP-001',
            'name' => 'Pokhara POP',
            'type' => 'pop',
            'status' => 'active',
            'latitude' => 28.2096,
            'longitude' => 83.9856,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sites', ['site_code' => 'PKR-POP-001', 'latitude' => 28.2096]);
    }

    public function test_invalid_latitude_is_rejected()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'INV-001',
            'name' => 'Invalid Site',
            'type' => 'pop',
            'status' => 'active',
            'latitude' => 91.0, // Invalid
        ]);

        $response->assertStatus(422);
    }

    public function test_site_can_belong_to_company_only()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'COMP-SITE-001',
            'name' => 'Company Site',
            'type' => 'office',
            'status' => 'active',
            'company_id' => $company->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sites', ['company_id' => $company->id, 'region_id' => null, 'branch_id' => null]);
    }

    public function test_site_can_belong_to_company_and_region()
    {
        $company = Company::factory()->create();
        $region = Region::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'REG-SITE-001',
            'name' => 'Regional Site',
            'type' => 'tower',
            'status' => 'active',
            'company_id' => $company->id,
            'region_id' => $region->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sites', ['region_id' => $region->id]);
    }

    public function test_site_can_belong_to_company_region_and_branch()
    {
        $company = Company::factory()->create();
        $region = Region::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['region_id' => $region->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'BR-SITE-001',
            'name' => 'Branch Site',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $company->id,
            'region_id' => $region->id,
            'branch_id' => $branch->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sites', ['branch_id' => $branch->id]);
    }

    public function test_region_must_belong_to_selected_company()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $region = Region::factory()->create(['company_id' => $company2->id]);
        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'ERR-001',
            'name' => 'Error Site',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $company1->id,
            'region_id' => $region->id, // Belongs to company2
        ]);

        $response->assertStatus(422);
    }

    public function test_branch_must_belong_to_selected_region()
    {
        $company = Company::factory()->create();
        $region1 = Region::factory()->create(['company_id' => $company->id]);
        $region2 = Region::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['region_id' => $region2->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.create');

        $response = $this->actingAs($user)->postJson('/api/v1/sites', [
            'site_code' => 'ERR-002',
            'name' => 'Error Site 2',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $company->id,
            'region_id' => $region1->id,
            'branch_id' => $branch->id, // Belongs to region2
        ]);

        $response->assertStatus(422);
    }

    public function test_can_view_sites()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');
        
        // Grant explicit company scope so user can see sites in this company
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/sites');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_update_site()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.update');

        // Grant scope
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/sites/{$site->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'Updated Name']);
    }

    public function test_can_soft_delete_site()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.delete');

        // Grant scope
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/sites/{$site->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('sites', ['id' => $site->id]);
    }

    public function test_summary_requires_authentication()
    {
        $response = $this->getJson('/api/v1/sites/summary');
        $response->assertStatus(401);
    }

    public function test_summary_requires_view_permission()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/v1/sites/summary');
        $response->assertStatus(403);
    }

    public function test_summary_returns_correct_counts()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $siteA = Site::factory()->create(['company_id' => $company->id]);
        $siteB = Site::factory()->create(['company_id' => $company->id]);
        $siteC = Site::factory()->create(['company_id' => $company->id]);

        Asset::factory()->count(2)->create(['site_id' => $siteA->id]);
        Asset::factory()->count(1)->create(['site_id' => $siteB->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/sites/summary');
        $response->assertStatus(200);
        $response->assertJsonPath('total_sites', 3);
        $response->assertJsonPath('sites_with_devices', 2);
        $response->assertJsonPath('sites_without_devices', 1);
        $response->assertJsonPath('total_devices', 3);
    }

    public function test_summary_returns_top_site()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $siteA = Site::factory()->create(['company_id' => $company->id, 'name' => 'Alpha Site', 'site_code' => 'ALPHA']);
        $siteB = Site::factory()->create(['company_id' => $company->id, 'name' => 'Beta Site', 'site_code' => 'BETA']);

        Asset::factory()->count(5)->create(['site_id' => $siteA->id]);
        Asset::factory()->count(10)->create(['site_id' => $siteB->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/sites/summary');
        $response->assertStatus(200);
        $response->assertJsonPath('top_site.name', 'Beta Site');
        $response->assertJsonPath('top_site.site_code', 'BETA');
        $response->assertJsonPath('top_site.device_count', 10);
    }

    public function test_summary_excludes_soft_deleted_assets()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset1 = Asset::factory()->create(['site_id' => $site->id]);
        $asset2 = Asset::factory()->create(['site_id' => $site->id]);
        
        $asset2->delete();

        $response = $this->actingAs($user)->getJson('/api/v1/sites/summary');
        $response->assertStatus(200);
        $response->assertJsonPath('total_devices', 1);
    }

    public function test_summary_respects_management_scope()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $user1 = User::factory()->create(['company_id' => $company1->id]);
        $user1->givePermissionTo('sites.view');
        UserManagementScope::create([
            'user_id' => $user1->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user1->id,
        ]);

        Site::factory()->count(3)->create(['company_id' => $company1->id]);
        Site::factory()->count(5)->create(['company_id' => $company2->id]);

        $response = $this->actingAs($user1)->getJson('/api/v1/sites/summary');
        $response->assertStatus(200);
        $response->assertJsonPath('total_sites', 3);
    }

    // =========================================================
    // TASK-036-D1 MANDATORY TESTS
    // =========================================================

    public function test_dashboard_total_matches_scoped_site_count()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(7)->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/sites/dashboard');
        $response->assertStatus(200);
        $response->assertJsonPath('data.total_sites', 7);
    }

    public function test_list_pagination_total_is_correct()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(23)->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/sites?per_page=10&page=1');
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 23);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonPath('meta.last_page', 3);
        $response->assertJsonCount(10, 'data');
    }

    public function test_dashboard_and_list_totals_are_consistent()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(12)->create(['company_id' => $company->id]);

        $dashboard = $this->actingAs($user)->getJson('/api/v1/sites/dashboard');
        $list = $this->actingAs($user)->getJson('/api/v1/sites?per_page=15&page=1');

        $this->assertEquals(
            $dashboard->json('data.total_sites'),
            $list->json('meta.total'),
            'Dashboard total_sites must equal list meta.total when no filters are active'
        );
    }

    public function test_case_insensitive_search()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $uniqueCode = 'SEARCH-' . strtoupper(uniqid());
        Site::factory()->create([
            'company_id' => $company->id,
            'name' => 'Jambukandh Tower',
            'site_code' => $uniqueCode,
        ]);

        $uppercase = $this->actingAs($user)->getJson('/api/v1/sites?search=' . strtoupper($uniqueCode));
        $lowercase = $this->actingAs($user)->getJson('/api/v1/sites?search=' . strtolower($uniqueCode));
        $mixed = $this->actingAs($user)->getJson('/api/v1/sites?search=SeArCh');

        $this->assertEquals(1, $uppercase->json('meta.total'));
        $this->assertEquals(1, $lowercase->json('meta.total'));
        // 'SeArCh' matches the site_code prefix
        $this->assertGreaterThanOrEqual(1, $mixed->json('meta.total'));
    }

    public function test_search_resets_pagination_correctly()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(20)->create(['company_id' => $company->id]);
        $uniqueName = 'UniqueTarget-' . uniqid();
        Site::factory()->create(['company_id' => $company->id, 'name' => $uniqueName]);

        $response = $this->actingAs($user)->getJson("/api/v1/sites?search={$uniqueName}&page=1&per_page=10");
        $response->assertStatus(200);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonCount(1, 'data');
    }

    public function test_management_scope_enforced_on_both_dashboard_and_list()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(5)->create(['company_id' => $company1->id]);
        Site::factory()->count(8)->create(['company_id' => $company2->id]);

        $dashboard = $this->actingAs($user)->getJson('/api/v1/sites/dashboard');
        $list = $this->actingAs($user)->getJson('/api/v1/sites?per_page=50');

        $dashboard->assertJsonPath('data.total_sites', 5);
        $list->assertJsonPath('meta.total', 5);
    }

    public function test_soft_deleted_sites_excluded_from_dashboard_and_list()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(3)->create(['company_id' => $company->id]);
        $deleted = Site::factory()->create(['company_id' => $company->id]);
        $deleted->delete();

        $dashboard = $this->actingAs($user)->getJson('/api/v1/sites/dashboard');
        $list = $this->actingAs($user)->getJson('/api/v1/sites?per_page=50');

        $dashboard->assertJsonPath('data.total_sites', 3);
        $list->assertJsonPath('meta.total', 3);
    }

    public function test_asset_counts_exclude_soft_deleted_assets()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $site = Site::factory()->create(['company_id' => $company->id]);
        Asset::factory()->count(3)->create(['site_id' => $site->id]);
        $deletedAsset = Asset::factory()->create(['site_id' => $site->id]);
        $deletedAsset->delete();

        $response = $this->actingAs($user)->getJson('/api/v1/sites?per_page=50');
        $response->assertStatus(200);

        $siteData = collect($response->json('data'))->firstWhere('id', $site->id);
        $this->assertEquals(3, $siteData['assets_count'], 'Soft-deleted assets must be excluded from count');
    }

    public function test_gps_fields_returned_in_api()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $site = Site::factory()->create([
            'company_id' => $company->id,
            'latitude' => 27.7172456,
            'longitude' => 85.3239605,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/sites/{$site->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.latitude', '27.7172456');
        $response->assertJsonPath('data.longitude', '85.3239605');
    }

    public function test_null_gps_serializes_safely()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $site = Site::factory()->create([
            'company_id' => $company->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/sites/{$site->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.latitude', null);
        $response->assertJsonPath('data.longitude', null);
    }

}
