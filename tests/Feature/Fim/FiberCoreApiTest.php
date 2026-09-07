<?php

namespace Tests\Feature\Fim;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiberCoreApiTest extends TestCase
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

    protected function segment(Company $company, int $fiberCount = 24): FiberSegment
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id, 'fiber_count' => $fiberCount]);
        $a = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $b = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        return FiberSegment::factory()->create(['fiber_cable_id' => $cable->id, 'endpoint_a_id' => $a->id, 'endpoint_b_id' => $b->id, 'company_id' => $company->id]);
    }

    protected function payload(FiberSegment $segment, array $overrides = []): array
    {
        return [...[
            'fiber_segment_id' => $segment->id,
            'core_number' => 1,
            'status' => 'available',
            'color_code' => 'blue',
            'metadata' => ['survey' => 'verified'],
        ], ...$overrides];
    }

    public function test_authentication_and_permissions_are_required(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company);
        $this->getJson('/api/v1/fim/fiber-cores')->assertUnauthorized();

        $user = $this->user($company, []);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/fiber-cores')->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($segment))->assertForbidden();
    }

    public function test_user_can_crud_a_segment_local_core_and_audit_changes(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company);
        $user = $this->user($company, ['fim.fiber-cores.create', 'fim.fiber-cores.view', 'fim.fiber-cores.update', 'fim.fiber-cores.delete', 'fim.fiber-segments.create', 'fim.fiber-segments.view', 'fim.fiber-segments.update', 'fim.fiber-segments.delete']);

        $create = $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($segment));
        $create->assertCreated()->assertJsonPath('data.fiber_segment_id', $segment->id)->assertJsonPath('data.color_code', 'blue');
        $id = $create->json('data.id');

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-segments/{$segment->id}/cores")
            ->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-cores/{$id}", ['core_number' => 3, 'status' => 'damaged', 'color_code' => 'orange'])
            ->assertOk()->assertJsonPath('data.core_number', 3)->assertJsonPath('data.status', 'damaged');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-cores/{$id}")->assertOk();

        $this->assertSoftDeleted('fiber_cores', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.fiber-core.created', 'target_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.fiber-core.updated', 'target_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.fiber-core.deleted', 'target_id' => $id]);
        $this->assertSame('orange', AuditLog::where('action', 'fim.fiber-core.updated')->firstOrFail()->metadata['changes']['color_code']);
    }

    public function test_scope_isolation_applies_to_parent_segment_and_core(): void
    {
        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        $segment = $this->segment($owner);
        $core = FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $owner->id, 'core_number' => 1, 'status' => 'available']);
        $user = $this->user($other, ['fim.fiber-cores.view', 'fim.fiber-cores.create', 'fim.fiber-cores.update', 'fim.fiber-cores.delete']);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-segments/{$segment->id}/cores")->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($segment))->assertUnprocessable()->assertJsonValidationErrors('fiber_segment_id');
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-cores/{$core->id}")->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-cores/{$core->id}", ['status' => 'damaged'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-cores/{$core->id}")->assertForbidden();
    }

    public function test_core_permissions_do_not_exceed_parent_segment_permissions(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company);
        $core = FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => 1, 'status' => 'available']);
        $user = $this->user($company, ['fim.fiber-cores.view', 'fim.fiber-cores.create', 'fim.fiber-cores.update', 'fim.fiber-cores.delete']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/fiber-cores')->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-segments/{$segment->id}/cores")->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($segment, ['core_number' => 2]))->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-cores/{$core->id}", ['status' => 'damaged'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-cores/{$core->id}")->assertForbidden();
    }

    public function test_numbering_is_positive_segment_local_and_gaps_are_allowed_without_continuity(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.fiber-cores.create', 'fim.fiber-segments.create']);
        $first = $this->segment($company);
        $second = $this->segment($company);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($first, ['core_number' => 1]))->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($first, ['core_number' => 3]))->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($second, ['core_number' => 1]))->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($first, ['core_number' => 1]))->assertUnprocessable()->assertJsonValidationErrors('core_number');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($first, ['core_number' => 0]))->assertUnprocessable()->assertJsonValidationErrors('core_number');

        $this->assertDatabaseCount('fiber_cores', 3);
        $this->assertArrayNotHasKey('continuity', (new FiberCore)->getAttributes());
    }

    public function test_cable_nominal_capacity_does_not_generate_or_reconcile_cores(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company, 48);
        $user = $this->user($company, ['fim.fiber-cores.create', 'fim.fiber-segments.create']);

        $this->assertDatabaseCount('fiber_cores', 0);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($segment, ['core_number' => 100]))->assertCreated();
        $segment->fiberCable->update(['fiber_count' => 2]);

        $this->assertDatabaseHas('fiber_cores', ['fiber_segment_id' => $segment->id, 'core_number' => 100]);
        $this->assertDatabaseCount('fiber_cores', 1);
    }

    public function test_generation_is_explicit_idempotent_and_audited(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company);
        $user = $this->user($company, ['fim.fiber-cores.create', 'fim.fiber-segments.create']);
        $payload = ['start_core_number' => 1, 'count' => 3];

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-segments/{$segment->id}/cores/generate", $payload)
            ->assertCreated()->assertJsonCount(3, 'data')->assertJsonPath('data.0.color_code', null)->assertJsonPath('meta.created_count', 3)->assertJsonPath('meta.skipped_existing_count', 0);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-segments/{$segment->id}/cores/generate", $payload)
            ->assertCreated()->assertJsonPath('meta.created_count', 0)->assertJsonPath('meta.skipped_existing_count', 3);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-segments/{$segment->id}/cores/generate", ['start_core_number' => 2, 'count' => 4])
            ->assertCreated()->assertJsonPath('meta.created_count', 2)->assertJsonPath('meta.skipped_existing_count', 2);

        $this->assertDatabaseCount('fiber_cores', 5);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.fiber-core.bulk-generated', 'target_id' => $segment->id]);
    }

    public function test_live_core_prevents_parent_segment_soft_delete_through_api_and_database(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company);
        FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => 1, 'status' => 'available']);
        $user = $this->user($company, ['fim.fiber-segments.delete']);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-segments/{$segment->id}")->assertUnprocessable();
        $this->expectException(QueryException::class);
        \DB::table('fiber_segments')->where('id', $segment->id)->update(['deleted_at' => now()]);
    }

    public function test_status_and_parent_company_integrity_are_enforced(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $segment = $this->segment($company);
        $user = $this->user($company, ['fim.fiber-cores.create', 'fim.fiber-segments.create']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($segment, ['status' => 'in_use']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->expectException(QueryException::class);
        \DB::table('fiber_cores')->insert([
            'fiber_segment_id' => $segment->id,
            'company_id' => $other->id,
            'core_number' => 1,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_deleted_core_numbers_remain_reserved(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company);
        $user = $this->user($company, ['fim.fiber-cores.create', 'fim.fiber-cores.delete', 'fim.fiber-segments.create', 'fim.fiber-segments.delete']);
        $core = FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => 1, 'status' => 'available']);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-cores/{$core->id}")->assertOk();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/fiber-cores', $this->payload($segment))
            ->assertUnprocessable()->assertJsonValidationErrors('core_number');
    }
}
