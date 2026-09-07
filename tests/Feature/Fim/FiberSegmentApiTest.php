<?php

namespace Tests\Feature\Fim;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberSegment;
use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiberSegmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);

        return $user->refresh();
    }

    /** @return array{0: FiberCable, 1: NetworkConnectionPoint, 2: NetworkConnectionPoint} */
    protected function topology(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $a = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $b = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        return [$cable, $a, $b];
    }

    protected function payload(FiberCable $cable, NetworkConnectionPoint $a, NetworkConnectionPoint $b): array
    {
        return [
            'fiber_cable_id' => $cable->id,
            'endpoint_a_id' => $a->id,
            'endpoint_b_id' => $b->id,
            'sequence' => 1,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[81.52572, 28.97354], [81.52610, 28.97400]]],
            'length_meters' => 100.50,
            'status' => 'installed',
        ];
    }

    public function test_user_can_create_read_update_and_soft_delete_a_segment(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.fiber-segments.create', 'fim.fiber-segments.view', 'fim.fiber-segments.update', 'fim.fiber-segments.delete']);
        [$cable, $a, $b] = $this->topology($company);

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $this->payload($cable, $a, $b));
        $create->assertCreated()->assertJsonPath('data.endpoint_a_id', $a->id)->assertJsonPath('data.endpoint_b_id', $b->id);
        $id = $create->json('data.id');

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-segments/{$id}")
            ->assertOk()->assertJsonPath('data.fiber_cable_id', $cable->id);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-segments/{$id}", ['status' => 'active'])
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-segments/{$id}")->assertOk();
        $this->assertSoftDeleted('fiber_segments', ['id' => $id]);
    }

    public function test_same_endpoint_and_missing_endpoints_are_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.fiber-segments.create']);
        [$cable, $a, $b] = $this->topology($company);

        $same = $this->payload($cable, $a, $a);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $same)->assertUnprocessable()->assertJsonValidationErrors('endpoint_b_id');

        $missing = $this->payload($cable, $a, $b);
        $missing['endpoint_b_id'] = 999999;
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $missing)->assertUnprocessable()->assertJsonValidationErrors('endpoint_b_id');
    }

    public function test_cross_company_endpoint_is_rejected(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['fim.fiber-segments.create']);
        [$cable, $a] = $this->topology($company);
        [, , $otherEndpoint] = $this->topology($other);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $this->payload($cable, $a, $otherEndpoint))
            ->assertUnprocessable()->assertJsonValidationErrors('endpoint_b_id');
    }

    public function test_sequence_is_unique_within_cable_but_parallel_endpoints_are_allowed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.fiber-segments.create']);
        [$cable, $a, $b] = $this->topology($company);
        $payload = $this->payload($cable, $a, $b);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $payload)->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $payload)->assertUnprocessable()->assertJsonValidationErrors('sequence');

        $payload['sequence'] = 2;
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $payload)->assertCreated();
    }

    public function test_segment_audit_log_uses_compact_geometry_summary(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.fiber-segments.create']);
        [$cable, $a, $b] = $this->topology($company);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $this->payload($cable, $a, $b))->assertCreated();
        $audit = AuditLog::where('action', 'fim.fiber-segment.created')->firstOrFail();
        $this->assertArrayHasKey('route_hash', $audit->metadata['geometry']);
        $this->assertArrayNotHasKey('coordinates', $audit->metadata['geometry']);
    }

    public function test_referenced_connection_point_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.fiber-segments.create', 'fim.connection-points.delete']);
        [$cable, $a, $b] = $this->topology($company);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-segments', $this->payload($cable, $a, $b))->assertCreated();

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/connection-points/{$a->id}")->assertUnprocessable();
        $this->assertDatabaseHas('network_connection_points', ['id' => $a->id, 'deleted_at' => null]);
    }

    public function test_database_rejects_non_api_endpoint_soft_delete(): void
    {
        $company = Company::factory()->create();
        [$cable, $a, $b] = $this->topology($company);
        FiberSegment::factory()->create([
            'fiber_cable_id' => $cable->id,
            'endpoint_a_id' => $a->id,
            'endpoint_b_id' => $b->id,
            'company_id' => $company->id,
            'sequence' => 1,
        ]);

        $this->expectException(QueryException::class);
        \DB::table('network_connection_points')->where('id', $a->id)->update(['deleted_at' => now()]);
    }

    public function test_database_rejects_cross_company_segment(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        [$cable, $a] = $this->topology($company);
        [, , $otherEndpoint] = $this->topology($other);

        $this->expectException(QueryException::class);
        \DB::table('fiber_segments')->insert([
            'fiber_cable_id' => $cable->id,
            'endpoint_a_id' => $a->id,
            'endpoint_b_id' => $otherEndpoint->id,
            'company_id' => $company->id,
            'sequence' => 2,
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
