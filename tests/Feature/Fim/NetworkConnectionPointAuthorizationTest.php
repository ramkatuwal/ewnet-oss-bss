<?php

namespace Tests\Feature\Fim;

use App\Models\Company;
use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkConnectionPointAuthorizationTest extends TestCase
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

    public function test_company_scope_grants_access(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.view']);
        $site = Site::factory()->create(['company_id' => $company->id]);
        $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/connection-points/{$point->id}");

        $response->assertStatus(200);
    }

    public function test_different_company_point_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.view']);

        $otherCompany = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $otherCompany->id]);
        $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $otherCompany->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/connection-points/{$point->id}");

        $response->assertStatus(403);
    }

    public function test_region_scope_denies_cable_access(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.view']);

        $region = $company->regions()->first();

        if ($region) {
            $site = Site::factory()->create(['company_id' => $company->id, 'region_id' => $region->id]);
            $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson("/api/v1/fim/connection-points/{$point->id}");

            $response->assertStatus(403);
        } else {
            $this->markTestSkipped('No region available for test company.');
        }
    }

    public function test_permission_required_for_create(): void
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
}
