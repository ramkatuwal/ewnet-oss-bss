<?php

namespace Tests\Feature\Fim;

use App\Models\Company;
use App\Models\FiberCable;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FiberCableApiTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(Company $company, ?Site $start = null, ?Site $end = null): array
    {
        return [
            'company_id' => $company->id,
            'cable_code' => 'FC-TEST-001',
            'name' => 'POP A to POP B Backbone',
            'cable_type' => 'backbone',
            'fiber_count' => 48,
            'status' => 'installed',
            'start_site_id' => $start?->id,
            'end_site_id' => $end?->id,
            'route_geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [81.52572, 28.97354],
                    [81.52610, 28.97400],
                ],
            ],
            'length_meters' => 1250.50,
        ];
    }

    public function test_can_create_fiber_cable_with_valid_geometries()
    {
        $company = Company::factory()->create();
        $start = Site::factory()->create(['company_id' => $company->id]);
        $end = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->scopedUser($company, ['fim.cables.create', 'fim.cables.view']);

        $response = $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $this->validPayload($company, $start, $end));

        $response->assertStatus(201);
        $response->assertJsonPath('data.cable_code', 'FC-TEST-001');
        $response->assertJsonPath('data.route_geometry.type', 'LineString');
        $this->assertDatabaseHas('fiber_cables', ['cable_code' => 'FC-TEST-001', 'company_id' => $company->id]);

        $row = DB::selectOne(
            'SELECT ST_SRID(route_geometry) AS srid, ST_GeometryType(route_geometry) AS gtype, ST_IsEmpty(route_geometry) AS empty FROM fiber_cables WHERE cable_code = ?',
            ['FC-TEST-001']
        );
        $this->assertEquals(4326, (int) $row->srid);
        $this->assertEquals('ST_LineString', $row->gtype);
        $this->assertFalse((bool) $row->empty);
    }

    public function test_rejects_duplicate_cable_code_within_company()
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.cables.create']);

        $payload = $this->validPayload($company);
        $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)->assertStatus(201);

        $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cable_code']);
    }

    public function test_soft_deleted_cable_code_remains_reserved()
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.cables.create', 'fim.cables.view', 'fim.cables.delete']);

        $payload = $this->validPayload($company);
        $id = $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)->assertStatus(201)->json('data.id');

        $this->actingAs($user)->deleteJson("/api/v1/fim/fiber-cables/{$id}")->assertStatus(200);
        $this->assertSoftDeleted('fiber_cables', ['id' => $id]);

        // Same code must not be silently reusable after soft deletion.
        $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cable_code']);
    }

    public function test_rejects_invalid_fiber_count()
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.cables.create']);

        foreach ([0, -12] as $count) {
            $payload = $this->validPayload($company);
            $payload['cable_code'] = 'FC-COUNT-'.$count;
            $payload['fiber_count'] = $count;
            $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['fiber_count']);
        }

        $payload = $this->validPayload($company);
        $payload['cable_code'] = 'FC-COUNT-STR';
        $payload['fiber_count'] = 'forty-eight';
        $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fiber_count']);
    }

    public function test_rejects_cross_company_endpoint_site()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $foreignSite = Site::factory()->create(['company_id' => $companyB->id]);
        $user = $this->scopedUser($companyA, ['fim.cables.create']);

        $payload = $this->validPayload($companyA);
        $payload['start_site_id'] = $foreignSite->id;
        $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_site_id']);
    }

    public function test_audit_logged_on_create_update_delete()
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.cables.create', 'fim.cables.view', 'fim.cables.update', 'fim.cables.delete']);

        $id = $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $this->validPayload($company))
            ->assertStatus(201)->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.cable.created']);

        $this->actingAs($user)->putJson("/api/v1/fim/fiber-cables/{$id}", ['name' => 'Renamed cable'])
            ->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.cable.updated']);

        $this->actingAs($user)->deleteJson("/api/v1/fim/fiber-cables/{$id}")->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.cable.deleted']);
    }

    public function test_soft_delete_hides_cable_from_list()
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.cables.create', 'fim.cables.view', 'fim.cables.delete']);

        $id = $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $this->validPayload($company))
            ->assertStatus(201)->json('data.id');

        $this->actingAs($user)->deleteJson("/api/v1/fim/fiber-cables/{$id}")->assertStatus(200);

        $list = $this->actingAs($user)->getJson('/api/v1/fim/fiber-cables')->assertStatus(200);
        $this->assertEquals(0, $list->json('meta.total'));
        $this->assertNotNull(FiberCable::withTrashed()->find($id)->deleted_at);
    }

    public function test_site_deletion_does_not_delete_cable()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->scopedUser($company, ['fim.cables.create', 'fim.cables.view', 'sites.delete']);

        $payload = $this->validPayload($company, $site);
        $id = $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $payload)->assertStatus(201)->json('data.id');

        // Site soft-delete preserves the anchor reference and must not remove the cable.
        $site->delete();
        $this->assertDatabaseHas('fiber_cables', ['id' => $id]);
        $this->assertEquals($site->id, FiberCable::find($id)->start_site_id);

        // Physical hard-delete nulls the coarse anchor but keeps cable history.
        $site->forceDelete();
        $this->assertDatabaseHas('fiber_cables', ['id' => $id]);
        $this->assertNull(FiberCable::find($id)->start_site_id);
    }
}
