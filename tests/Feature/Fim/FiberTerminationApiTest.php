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

class FiberTerminationApiTest extends TestCase
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

    /** @return array{0: FiberCore, 1: FiberSegment, 2: NetworkConnectionPoint, 3: NetworkConnectionPoint} */
    protected function topology(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $a = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $b = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $segment = FiberSegment::factory()->create(['fiber_cable_id' => $cable->id, 'endpoint_a_id' => $a->id, 'endpoint_b_id' => $b->id, 'company_id' => $company->id]);
        $core = FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => 1, 'status' => 'available']);

        return [$core, $segment, $a, $b];
    }

    protected function permissions(string ...$termination): array
    {
        return [...$termination, 'fim.fiber-cores.view', 'fim.fiber-cores.create', 'fim.fiber-cores.update', 'fim.fiber-cores.delete', 'fim.fiber-segments.view', 'fim.fiber-segments.create', 'fim.fiber-segments.update', 'fim.fiber-segments.delete', 'fim.connection-points.view'];
    }

    public function test_authentication_and_parent_permissions_are_required(): void
    {
        $company = Company::factory()->create();
        [$core, , $a] = $this->topology($company);
        $this->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A'])->assertUnauthorized();

        $user = $this->user($company, ['fim.fiber-terminations.create']);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A'])->assertForbidden();

        $user = $this->user($company, ['fim.fiber-terminations.create', 'fim.fiber-cores.create', 'fim.connection-points.view']);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A'])->assertForbidden();
    }

    public function test_company_scope_and_composite_foreign_keys_prevent_cross_company_termination(): void
    {
        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        [$core, , $a] = $this->topology($owner);
        $user = $this->user($other, $this->permissions('fim.fiber-terminations.create'));

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A'])->assertForbidden();
        $this->expectException(QueryException::class);
        \DB::table('fiber_terminations')->insert(['fiber_core_id' => $core->id, 'company_id' => $other->id, 'network_connection_point_id' => $a->id, 'segment_end' => 'A', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_zero_one_or_two_segment_end_terminations_are_valid_and_audit_safe(): void
    {
        $company = Company::factory()->create();
        [$core, , $a, $b] = $this->topology($company);
        $user = $this->user($company, $this->permissions('fim.fiber-terminations.view', 'fim.fiber-terminations.create', 'fim.fiber-terminations.update', 'fim.fiber-terminations.delete'));

        $this->assertDatabaseCount('fiber_terminations', 0);
        $first = $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A', 'metadata' => ['survey' => 'confirmed']]);
        $first->assertCreated()->assertJsonPath('data.segment_end', 'A')->assertJsonPath('data.network_connection_point_id', $a->id);
        $id = $first->json('data.id');
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $b->id, 'segment_end' => 'B'])->assertCreated();
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-cores/{$core->id}/terminations")->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-terminations/{$id}", ['metadata' => ['survey' => 'corrected']])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.fiber-termination.created', 'target_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.fiber-termination.updated', 'target_id' => $id]);
        $this->assertTrue(AuditLog::where('action', 'fim.fiber-termination.updated')->firstOrFail()->metadata['metadata_changed']);
    }

    public function test_wrong_endpoint_duplicate_and_identity_mutation_are_rejected(): void
    {
        $company = Company::factory()->create();
        [$core, , $a, $b] = $this->topology($company);
        $user = $this->user($company, $this->permissions('fim.fiber-terminations.create', 'fim.fiber-terminations.update'));

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $b->id, 'segment_end' => 'A'])->assertUnprocessable()->assertJsonValidationErrors('network_connection_point_id');
        $created = $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A']);
        $id = $created->json('data.id');
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$core->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-terminations/{$id}", ['segment_end' => 'B', 'fiber_core_id' => $core->id])->assertUnprocessable();
    }

    public function test_database_enforces_endpoint_consistency(): void
    {
        $company = Company::factory()->create();
        [$core, , $a, $b] = $this->topology($company);
        $this->expectException(QueryException::class);
        \DB::table('fiber_terminations')->insert(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $b->id, 'segment_end' => 'A', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_accepts_both_valid_endpoints_and_blocks_live_segment_endpoint_changes(): void
    {
        $company = Company::factory()->create();
        [$core, $segment, $a, $b] = $this->topology($company);
        \DB::table('fiber_terminations')->insert([
            ['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $a->id, 'segment_end' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $b->id, 'segment_end' => 'B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $replacement = NetworkConnectionPoint::factory()->create(['company_id' => $company->id]);
        $user = $this->user($company, $this->permissions('fim.fiber-segments.update'));

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-segments/{$segment->id}", ['endpoint_a_id' => $replacement->id])->assertUnprocessable();
        $this->expectException(QueryException::class);
        \DB::table('fiber_segments')->where('id', $segment->id)->update(['endpoint_b_id' => $replacement->id]);
    }

    public function test_historical_termination_does_not_block_core_or_segment_correction(): void
    {
        $company = Company::factory()->create();
        [$core, $segment, $a] = $this->topology($company);
        \DB::table('fiber_terminations')->insert(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $a->id, 'segment_end' => 'A', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now()]);
        $replacement = NetworkConnectionPoint::factory()->create(['company_id' => $company->id]);

        \DB::table('fiber_segments')->where('id', $segment->id)->update(['endpoint_a_id' => $replacement->id]);
        \DB::table('fiber_cores')->where('id', $core->id)->update(['deleted_at' => now()]);
        $this->assertSoftDeleted('fiber_cores', ['id' => $core->id]);
    }

    public function test_database_and_api_block_core_soft_delete_with_live_terminations(): void
    {
        $company = Company::factory()->create();
        [$core, , $a] = $this->topology($company);
        \DB::table('fiber_terminations')->insert(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $a->id, 'segment_end' => 'A', 'created_at' => now(), 'updated_at' => now()]);
        $user = $this->user($company, $this->permissions('fim.fiber-cores.delete'));

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-cores/{$core->id}")->assertUnprocessable();
        $this->expectException(QueryException::class);
        \DB::table('fiber_cores')->where('id', $core->id)->update(['deleted_at' => now()]);
    }

    public function test_same_ncp_can_host_different_core_terminations_and_deleted_end_is_reserved(): void
    {
        $company = Company::factory()->create();
        [$first, $segment, $a, $b] = $this->topology($company);
        $second = FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => 2, 'status' => 'available']);
        $user = $this->user($company, $this->permissions('fim.fiber-terminations.create', 'fim.fiber-terminations.delete'));
        $firstTermination = $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$first->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A']);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$second->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A'])->assertCreated();
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/fim/fiber-terminations/'.$firstTermination->json('data.id'))->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$first->id}/terminations", ['network_connection_point_id' => $a->id, 'segment_end' => 'A'])->assertUnprocessable();
        $secondEnd = $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$first->id}/terminations", ['network_connection_point_id' => $b->id, 'segment_end' => 'B']);
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/fim/fiber-terminations/'.$secondEnd->json('data.id'))->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/fiber-cores/{$first->id}/terminations", ['network_connection_point_id' => $b->id, 'segment_end' => 'B'])->assertUnprocessable();
    }
}
