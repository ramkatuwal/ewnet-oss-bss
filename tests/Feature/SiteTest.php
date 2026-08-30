<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Site;
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
}
