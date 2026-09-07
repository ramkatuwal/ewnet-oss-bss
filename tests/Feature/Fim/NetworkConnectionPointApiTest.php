<?php

namespace Tests\Feature\Fim;

use App\Models\Company;
use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkConnectionPointApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function scopedUser(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        return $user->refresh();
    }

    public function test_authenticated_user_can_create_connection_point_with_site_anchor(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.create']);
        $site = Site::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'odf',
                'name' => 'Test ODF',
                'site_id' => $site->id,
                'company_id' => $company->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'point_type', 'name', 'site_id', 'company_id']]);
    }

    public function test_authenticated_user_can_create_connection_point_with_standalone_geometry(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.create']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'standalone',
                'name' => 'Standalone Point',
                'geometry' => ['type' => 'Point', 'coordinates' => [81.52572, 28.97354]],
                'company_id' => $company->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201);
    }

    public function test_requires_at_least_one_anchor_or_geometry(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.create']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'odf',
                'name' => 'No Anchor',
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('base');
    }

    public function test_cross_company_site_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.create']);

        $site = Site::factory()->create();
        $otherCompany = Company::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'odf',
                'name' => 'Cross Company',
                'site_id' => $site->id,
                'company_id' => $otherCompany->id,
                'status' => 'active',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('site_id');
    }

    public function test_unauthorized_user_cannot_create(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'odf',
                'name' => 'No Permission',
                'site_id' => $site->id,
                'company_id' => Site::first()->company_id,
                'status' => 'active',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_list_connection_points(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.view']);
        NetworkConnectionPoint::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/fim/connection-points');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => 'data', 'meta']);
    }

    public function test_scope_restricts_connection_points(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.view']);

        $otherCompany = Company::factory()->create();
        $siteA = Site::factory()->create(['company_id' => $company->id]);
        $siteB = Site::factory()->create(['company_id' => $otherCompany->id]);

        NetworkConnectionPoint::factory()->create(['site_id' => $siteA->id, 'company_id' => $company->id]);
        NetworkConnectionPoint::factory()->create(['site_id' => $siteB->id, 'company_id' => $otherCompany->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/fim/connection-points');

        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_empty_results_returns_empty(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/fim/connection-points');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
