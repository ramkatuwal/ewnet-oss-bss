<?php

namespace Tests\Feature\Fim;

use App\Models\Company;
use App\Models\FiberCable;
use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiberSegmentGeometryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function setupTopology(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('fim.fiber-segments.create');
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        $site = Site::factory()->create(['company_id' => $company->id]);

        return [$user, FiberCable::factory()->create(['company_id' => $company->id]), NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]), NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id])];
    }

    protected function payload($cable, $a, $b, mixed $geometry): array
    {
        return ['fiber_cable_id' => $cable->id, 'endpoint_a_id' => $a->id, 'endpoint_b_id' => $b->id, 'sequence' => 1, 'status' => 'installed', 'geometry' => $geometry];
    }

    public function test_linestring_is_stored_with_srid_and_geography_length(): void
    {
        [$user, $cable, $a, $b] = $this->setupTopology();
        $geometry = ['type' => 'LineString', 'coordinates' => [[81.52572, 28.97354], [81.52610, 28.97400]]];
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $this->payload($cable, $a, $b, $geometry));
        $response->assertCreated()->assertJsonPath('data.geometry.type', 'LineString');
        $id = $response->json('data.id');
        $raw = \DB::selectOne('SELECT ST_SRID(geometry) srid, ST_GeometryType(geometry) type, ST_Length(geometry::geography) meters FROM fiber_segments WHERE id = ?', [$id]);
        $this->assertSame(4326, (int) $raw->srid);
        $this->assertSame('ST_LineString', $raw->type);
        $this->assertGreaterThan(0, $raw->meters);
        $this->assertGreaterThan(0, $response->json('data.calculated_length_meters'));
    }

    public function test_invalid_geometry_types_coordinates_and_malformed_values_are_rejected(): void
    {
        [$user, $cable, $a, $b] = $this->setupTopology();
        foreach ([
            ['type' => 'Point', 'coordinates' => [81.5, 28.9]],
            ['type' => 'Polygon', 'coordinates' => []],
            ['type' => 'MultiLineString', 'coordinates' => []],
            ['type' => 'LineString', 'coordinates' => []],
            ['type' => 'LineString', 'coordinates' => [[81.5, 28.9], [81.5, 28.9]]],
            ['type' => 'LineString', 'coordinates' => [[181, 28.9], [81.5, 28.9]]],
            'not-geojson',
        ] as $geometry) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $this->payload($cable, $a, $b, $geometry))
                ->assertUnprocessable()->assertJsonValidationErrors('geometry');
        }
    }

    public function test_geometry_authority_transitions_without_restoring_stale_routes(): void
    {
        [$user, $cable, $a, $b] = $this->setupTopology();
        $user->givePermissionTo(['fim.cables.update', 'fim.fiber-segments.update', 'fim.fiber-segments.delete']);
        $firstGeometry = ['type' => 'LineString', 'coordinates' => [[81.52572, 28.97354], [81.52610, 28.97400]]];
        // The second span is reverse-oriented and deliberately has a sequence gap;
        // spatial merging must not concatenate coordinates by sequence.
        $secondGeometry = ['type' => 'LineString', 'coordinates' => [[81.52660, 28.97440], [81.52610, 28.97400]]];

        $this->assertSame('cable', \DB::table('fiber_cables')->where('id', $cable->id)->value('route_geometry_authority'));
        $first = $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $this->payload($cable, $a, $b, $firstGeometry));
        $first->assertCreated();
        $firstId = $first->json('data.id');

        $parent = \DB::table('fiber_cables')->where('id', $cable->id)->first();
        $this->assertSame('segments', $parent->route_geometry_authority);
        $this->assertNotNull($parent->route_geometry);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-cables/{$cable->id}", ['route_geometry' => $firstGeometry])
            ->assertUnprocessable()->assertJsonValidationErrors('route_geometry');

        $second = $this->payload($cable, $a, $b, $secondGeometry);
        $second['sequence'] = 3;
        $secondId = $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $second)->assertCreated()->json('data.id');
        $this->assertSame('ST_LineString', \DB::selectOne('SELECT ST_GeometryType(route_geometry) AS type FROM fiber_cables WHERE id = ?', [$cable->id])->type);

        $disjointGeometry = ['type' => 'LineString', 'coordinates' => [[82.00000, 29.00000], [82.00100, 29.00100]]];
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-segments/{$secondId}", ['geometry' => $disjointGeometry])->assertOk();
        $this->assertNull(\DB::table('fiber_cables')->where('id', $cable->id)->value('route_geometry'));

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-segments/{$secondId}")->assertOk();
        $this->assertNotNull(\DB::table('fiber_cables')->where('id', $cable->id)->value('route_geometry'));
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-segments/{$firstId}")->assertOk();

        $parent = \DB::table('fiber_cables')->where('id', $cable->id)->first();
        $this->assertSame('cable', $parent->route_geometry_authority);
        $this->assertNull($parent->route_geometry);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-cables/{$cable->id}", ['route_geometry' => $firstGeometry])
            ->assertOk()->assertJsonPath('data.route_geometry_authority', 'cable')->assertJsonPath('data.route_geometry_available', true);
    }
}
