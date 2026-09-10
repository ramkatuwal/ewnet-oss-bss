<?php

namespace Tests\Feature\Fim;

use App\Models\Company;
use App\Models\FiberCable;
use App\Models\NetworkConnectionPoint;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FimMapApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);

        return $user->refresh();
    }

    private function route(FiberCable $cable): void
    {
        DB::update('UPDATE fiber_cables SET route_geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?', [json_encode(['type' => 'LineString', 'coordinates' => [[83.1, 28.1], [83.2, 28.2]]]), $cable->id]);
    }

    public function test_map_returns_only_authorized_postgis_features_in_viewport(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $hidden = FiberCable::factory()->create(['company_id' => $otherCompany->id]);
        $point = NetworkConnectionPoint::factory()->withoutSite()->create(['company_id' => $company->id]);
        $this->route($cable);
        $this->route($hidden);
        DB::update('UPDATE network_connection_points SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?', [json_encode(['type' => 'Point', 'coordinates' => [83.15, 28.15]]), $point->id]);
        $user = $this->user($company, ['fim.cables.view', 'fim.connection-points.view']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/map?west=83&south=28&east=84&north=29')
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(2, 'features')
            ->assertJsonMissing(['id' => 'cable:'.$hidden->id])
            ->assertJsonPath('features.0.properties.kind', 'cable')
            ->assertJsonMissingPath('features.0.properties.company_id');
    }

    public function test_map_rejects_invalid_or_excessive_viewports_and_hides_soft_deleted_rows(): void
    {
        $company = Company::factory()->create();
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $this->route($cable);
        $cable->delete();
        $user = $this->user($company, ['fim.cables.view']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/map?west=84&south=28&east=83&north=29')->assertUnprocessable()->assertJsonValidationErrors('bounds');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/map?west=0&south=0&east=20&north=1')->assertUnprocessable()->assertJsonValidationErrors('bounds');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/map?west=83&south=28&east=84&north=29&layers[]=cables')->assertOk()->assertJsonCount(0, 'features');
    }
}
