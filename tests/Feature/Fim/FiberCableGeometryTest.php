<?php

namespace Tests\Feature\Fim;

use App\Models\Company;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FiberCableGeometryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function user(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('fim.cables.create');
        $user->givePermissionTo('fim.cables.view');
        $user->givePermissionTo('fim.cables.update');
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function basePayload(Company $company, string $code, mixed $geometry): array
    {
        return [
            'company_id' => $company->id,
            'cable_code' => $code,
            'name' => 'Geometry test cable',
            'cable_type' => 'backbone',
            'fiber_count' => 48,
            'status' => 'installed',
            'route_geometry' => $geometry,
        ];
    }

    public function test_rejects_non_linestring_geometry_types()
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $cases = [
            'point' => ['type' => 'Point', 'coordinates' => [81.5, 28.9]],
            'polygon' => ['type' => 'Polygon', 'coordinates' => [[[81.5, 28.9], [81.6, 28.9], [81.6, 29.0], [81.5, 28.9]]]],
            'multilinestring' => ['type' => 'MultiLineString', 'coordinates' => [[[81.5, 28.9], [81.6, 29.0]]]],
            'collection' => ['type' => 'GeometryCollection', 'geometries' => []],
        ];

        $i = 0;
        foreach ($cases as $label => $geometry) {
            $response = $this->actingAs($user)->postJson(
                '/api/v1/fim/fiber-cables',
                $this->basePayload($company, 'FC-GEO-'.$i++, $geometry)
            );
            $response->assertStatus(422, "Failed asserting {$label} is rejected.");
            $response->assertJsonValidationErrors(['route_geometry']);
        }
    }

    public function test_rejects_malformed_geometry_structures()
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $cases = [
            'string' => 'LINESTRING(81.5 28.9, 81.6 29.0)',
            'null' => null,
            'empty' => ['type' => 'LineString', 'coordinates' => []],
            'single_point' => ['type' => 'LineString', 'coordinates' => [[81.5, 28.9]]],
            'non_array_coords' => ['type' => 'LineString', 'coordinates' => '81.5,28.9'],
            'one_dimensional' => ['type' => 'LineString', 'coordinates' => [[81.5], [81.6]]],
            'three_dimensional' => ['type' => 'LineString', 'coordinates' => [[81.5, 28.9, 10.0], [81.6, 29.0, 11.0]]],
            'string_coords' => ['type' => 'LineString', 'coordinates' => [['81.5', '28.9'], ['81.6', '29.0']]],
        ];

        $i = 0;
        foreach ($cases as $label => $geometry) {
            $response = $this->actingAs($user)->postJson(
                '/api/v1/fim/fiber-cables',
                $this->basePayload($company, 'FC-MAL-'.$i++, $geometry)
            );
            $response->assertStatus(422, "Failed asserting {$label} is rejected.");
            $response->assertJsonValidationErrors(['route_geometry']);
        }
    }

    public function test_rejects_out_of_range_coordinates()
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $cases = [
            'lng_too_low' => [[-181.0, 28.9], [81.6, 29.0]],
            'lng_too_high' => [[181.0, 28.9], [81.6, 29.0]],
            'lat_too_low' => [[81.5, -90.5], [81.6, 29.0]],
            'lat_too_high' => [[81.5, 90.5], [81.6, 29.0]],
        ];

        $i = 0;
        foreach ($cases as $label => $coords) {
            $response = $this->actingAs($user)->postJson(
                '/api/v1/fim/fiber-cables',
                $this->basePayload($company, 'FC-RNG-'.$i++, ['type' => 'LineString', 'coordinates' => $coords])
            );
            $response->assertStatus(422, "Failed asserting {$label} is rejected.");
            $response->assertJsonValidationErrors(['route_geometry']);
        }
    }

    public function test_rejects_degenerate_zero_length_route()
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $response = $this->actingAs($user)->postJson(
            '/api/v1/fim/fiber-cables',
            $this->basePayload($company, 'FC-DEG-001', [
                'type' => 'LineString',
                'coordinates' => [[81.5, 28.9], [81.5, 28.9], [81.5, 28.9]],
            ])
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['route_geometry']);
    }

    public function test_geometry_round_trips_as_geojson()
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $geometry = ['type' => 'LineString', 'coordinates' => [[81.52572, 28.97354], [81.52610, 28.97400]]];
        $id = $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $this->basePayload($company, 'FC-RT-001', $geometry))
            ->assertStatus(201)->json('data.id');

        $shown = $this->actingAs($user)->getJson("/api/v1/fim/fiber-cables/{$id}")->assertStatus(200);
        $this->assertEquals('LineString', $shown->json('data.route_geometry.type'));
        $this->assertEqualsWithDelta(81.52572, $shown->json('data.route_geometry.coordinates.0.0'), 0.00001);
        $this->assertEqualsWithDelta(28.97354, $shown->json('data.route_geometry.coordinates.0.1'), 0.00001);
    }

    public function test_geometry_update_audit_is_compact()
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $id = $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $this->basePayload($company, 'FC-AUD-001', [
            'type' => 'LineString', 'coordinates' => [[81.5, 28.9], [81.6, 29.0]],
        ]))->assertStatus(201)->json('data.id');

        $this->actingAs($user)->putJson("/api/v1/fim/fiber-cables/{$id}", [
            'route_geometry' => ['type' => 'LineString', 'coordinates' => [[81.5, 28.9], [81.7, 29.1]]],
        ])->assertStatus(200);

        $audit = DB::table('audit_logs')->where('action', 'fim.cable.updated')->orderByDesc('id')->first();
        $this->assertNotNull($audit);
        $metadata = json_decode($audit->metadata, true);
        $this->assertArrayHasKey('route_geometry', $metadata);
        $this->assertArrayHasKey('old', $metadata['route_geometry']);
        $this->assertArrayHasKey('new', $metadata['route_geometry']);
        $this->assertArrayHasKey('route_hash', $metadata['route_geometry']['new']);
        $this->assertArrayHasKey('point_count', $metadata['route_geometry']['new']);
        $this->assertArrayHasKey('bbox', $metadata['route_geometry']['new']);
        $this->assertArrayNotHasKey('coordinates', $metadata['route_geometry']['new']);
    }
}
