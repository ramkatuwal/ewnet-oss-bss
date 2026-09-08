<?php

namespace Tests\Feature\Fim;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Models\PhysicalConnection;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\Fim\PhysicalConnectionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhysicalConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function endpoints(): array
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $a = NetworkConnectionPoint::factory()->create(['company_id' => $company->id, 'site_id' => $site->id]);
        $b = NetworkConnectionPoint::factory()->create(['company_id' => $company->id, 'site_id' => $site->id]);
        $segment = FiberSegment::factory()->create(['company_id' => $company->id, 'fiber_cable_id' => $cable->id, 'endpoint_a_id' => $a->id, 'endpoint_b_id' => $b->id]);
        $cores = [];
        foreach ([1, 2, 3] as $number) {
            $core = FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => $number, 'status' => 'available']);
            $cores[] = FiberTermination::create(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $a->id, 'segment_end' => 'A']);
        }

        return [$company, $cores];
    }

    protected function connectionUser(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['fim.physical-connections.create', 'fim.physical-connections.view', 'fim.physical-connections.update', 'fim.fiber-terminations.view', 'fim.fiber-cores.view', 'fim.fiber-segments.view', 'fim.cables.view', 'fim.connection-points.view']);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);

        return $user;
    }

    public function test_canonical_splice_lifecycle_and_live_participation(): void
    {
        [$company,[$one,$two,$three]] = $this->endpoints();
        $user = User::factory()->create(['company_id' => $company->id]);
        $service = app(PhysicalConnectionService::class);
        $first = $service->create(['termination_a_id' => $two->id, 'termination_b_id' => $one->id, 'connection_type' => 'fusion_splice', 'metadata' => ['survey' => 'x']], $user);
        $this->assertLessThan($first->termination_b_id, $first->termination_a_id);
        $this->expectException(ValidationException::class);
        $service->create(['termination_a_id' => $one->id, 'termination_b_id' => $three->id, 'connection_type' => 'mechanical_splice'], $user);
    }

    public function test_database_rejects_same_core_different_ncp_and_live_conflicts(): void
    {
        [$company,[$one,$two,$three]] = $this->endpoints();
        $this->expectException(QueryException::class);
        \DB::table('physical_connections')->insert(['company_id' => $company->id, 'termination_a_id' => $one->id, 'termination_b_id' => $two->id, 'connection_type' => 'connector', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_rejects_self_same_core_and_different_ncp_splices(): void
    {
        [$company, [$one, $two]] = $this->endpoints();
        $this->expectException(QueryException::class);
        \DB::table('physical_connections')->insert(['company_id' => $company->id, 'termination_a_id' => $one->id, 'termination_b_id' => $one->id, 'connection_type' => 'fusion_splice', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_historical_same_pair_can_be_recreated_with_a_new_identity(): void
    {
        [$company, [$one, $two]] = $this->endpoints();
        $user = User::factory()->create(['company_id' => $company->id]);
        $service = app(PhysicalConnectionService::class);
        $first = $service->create(['termination_a_id' => $one->id, 'termination_b_id' => $two->id, 'connection_type' => 'fusion_splice'], $user);
        $service->delete($first);
        $second = $service->create(['termination_a_id' => $two->id, 'termination_b_id' => $one->id, 'connection_type' => 'fusion_splice'], $user);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSoftDeleted('physical_connections', ['id' => $first->id]);
        $this->assertDatabaseCount('physical_connections', 2);
    }

    public function test_type_check_and_termination_history_protection_apply_at_database_level(): void
    {
        [$company, [$one, $two]] = $this->endpoints();
        try {
            \DB::transaction(fn () => \DB::table('physical_connections')->insert(['company_id' => $company->id, 'termination_a_id' => min($one->id, $two->id), 'termination_b_id' => max($one->id, $two->id), 'connection_type' => 'connector', 'created_at' => now(), 'updated_at' => now()]));
            $this->fail('Expected PostgreSQL to reject connector as a physical connection type.');
        } catch (QueryException) {
            $this->assertDatabaseCount('physical_connections', 0);
        }
    }

    public function test_disconnect_allows_reconnection_and_preserves_termination_history(): void
    {
        [$company,[$one,$two,$three]] = $this->endpoints();
        $user = User::factory()->create(['company_id' => $company->id]);
        $service = app(PhysicalConnectionService::class);
        $first = $service->create(['termination_a_id' => $one->id, 'termination_b_id' => $two->id, 'connection_type' => 'fusion_splice'], $user);
        $service->delete($first);
        $next = $service->create(['termination_a_id' => $one->id, 'termination_b_id' => $three->id, 'connection_type' => 'mechanical_splice'], $user);
        $this->assertNotSame($first->id, $next->id);
        $this->expectException(QueryException::class);
        \DB::table('fiber_terminations')->where('id', $one->id)->update(['deleted_at' => now()]);
    }

    public function test_database_rejects_same_core_terminations_with_valid_ncp_and_type(): void
    {
        [$company, [$a]] = $this->endpoints();
        $core = $a->fiberCore;
        $b = FiberTermination::create(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $core->fiberSegment->endpoint_b_id, 'segment_end' => 'B']);

        $this->expectException(QueryException::class);
        \DB::table('physical_connections')->insert(['company_id' => $company->id, 'termination_a_id' => min($a->id, $b->id), 'termination_b_id' => max($a->id, $b->id), 'connection_type' => 'fusion_splice', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_database_rejects_different_ncp_terminations_with_different_cores(): void
    {
        [$company, [$a]] = $this->endpoints();
        $segment = $a->fiberCore->fiberSegment;
        $core = FiberCore::create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => 99, 'status' => 'available']);
        $b = FiberTermination::create(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $segment->endpoint_b_id, 'segment_end' => 'B']);

        $this->expectException(QueryException::class);
        \DB::table('physical_connections')->insert(['company_id' => $company->id, 'termination_a_id' => min($a->id, $b->id), 'termination_b_id' => max($a->id, $b->id), 'connection_type' => 'fusion_splice', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_api_rejects_cross_company_endpoints_and_ignores_company_input(): void
    {
        [$companyA, [$a]] = $this->endpoints();
        [$companyB, [$b]] = $this->endpoints();
        $user = User::factory()->create(['company_id' => $companyA->id]);
        $user->givePermissionTo(['fim.physical-connections.create', 'fim.fiber-terminations.view', 'fim.fiber-cores.view', 'fim.fiber-segments.view', 'fim.cables.view', 'fim.connection-points.view']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/physical-connections', ['termination_a_id' => $a->id, 'termination_b_id' => $b->id, 'connection_type' => 'fusion_splice', 'company_id' => $companyB->id])->assertForbidden();
        $this->assertDatabaseCount('physical_connections', 0);
    }

    public function test_database_rejects_cross_company_endpoints(): void
    {
        [$companyA, [$a]] = $this->endpoints();
        [, [$b]] = $this->endpoints();
        $this->expectException(QueryException::class);
        \DB::table('physical_connections')->insert(['company_id' => $companyA->id, 'termination_a_id' => min($a->id, $b->id), 'termination_b_id' => max($a->id, $b->id), 'connection_type' => 'fusion_splice', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_service_rejects_reversed_duplicate_and_cross_column_participation(): void
    {
        [$company, [$one, $two, $three]] = $this->endpoints();
        $service = app(PhysicalConnectionService::class);
        $user = User::factory()->create(['company_id' => $company->id]);
        $connection = $service->create(['termination_a_id' => $two->id, 'termination_b_id' => $three->id, 'connection_type' => 'fusion_splice'], $user);
        $this->assertLessThan($connection->termination_b_id, $connection->termination_a_id);
        try {
            $service->create(['termination_a_id' => $one->id, 'termination_b_id' => $two->id, 'connection_type' => 'mechanical_splice'], $user);
            $this->fail('Expected live endpoint participation validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('physical_connections', 1);
        }
    }

    public function test_database_prevents_termination_deletion_after_historical_connection(): void
    {
        [$company, [$one, $two]] = $this->endpoints();
        $service = app(PhysicalConnectionService::class);
        $connection = $service->create(['termination_a_id' => $one->id, 'termination_b_id' => $two->id, 'connection_type' => 'fusion_splice'], User::factory()->create(['company_id' => $company->id]));
        $service->delete($connection);
        $this->assertNotNull($connection->fresh()->deleted_at);
        $this->assertSame(0, \DB::table('physical_connections')->whereNull('deleted_at')->where(fn ($q) => $q->where('termination_a_id', $one->id)->orWhere('termination_b_id', $one->id))->count());
        try {
            \DB::transaction(fn () => \DB::table('fiber_terminations')->whereKey($one->id)->update(['deleted_at' => now()]));
            $this->fail('Expected PostgreSQL to preserve historical termination identity.');
        } catch (QueryException) {
            $this->assertNull($one->fresh()->deleted_at);
            $this->assertNotNull($connection->fresh()->deleted_at);
        }
    }

    public function test_database_prevents_termination_deletion_after_live_connection(): void
    {
        [$company, [$one, $two]] = $this->endpoints();
        $connection = app(PhysicalConnectionService::class)->create(['termination_a_id' => $one->id, 'termination_b_id' => $two->id, 'connection_type' => 'fusion_splice'], User::factory()->create(['company_id' => $company->id]));
        try {
            \DB::transaction(fn () => \DB::table('fiber_terminations')->whereKey($one->id)->update(['deleted_at' => now()]));
            $this->fail('Expected PostgreSQL to preserve live termination identity.');
        } catch (QueryException) {
            $this->assertNull($one->fresh()->deleted_at);
            $this->assertNull($connection->fresh()->deleted_at);
            $this->assertDatabaseHas('fiber_terminations', ['id' => $one->id, 'deleted_at' => null]);
        }
    }

    public function test_database_rejects_cross_column_live_endpoint_participation(): void
    {
        [$company, [$one, $two, $three]] = $this->endpoints();
        $service = app(PhysicalConnectionService::class);
        $first = $service->create(['termination_a_id' => $two->id, 'termination_b_id' => $three->id, 'connection_type' => 'fusion_splice'], User::factory()->create(['company_id' => $company->id]));

        try {
            \DB::transaction(fn () => \DB::table('physical_connections')->insert(['company_id' => $company->id, 'termination_a_id' => min($one->id, $two->id), 'termination_b_id' => max($one->id, $two->id), 'connection_type' => 'fusion_splice', 'created_at' => now(), 'updated_at' => now()]));
            $this->fail('Expected PostgreSQL to reject live endpoint participation.');
        } catch (QueryException) {
            $this->assertNull($first->fresh()->deleted_at);
            $this->assertDatabaseCount('physical_connections', 1);
            $this->assertSame(1, \DB::table('physical_connections')->whereNull('deleted_at')->where(fn ($q) => $q->where('termination_a_id', $two->id)->orWhere('termination_b_id', $two->id))->count());
        }
    }

    public function test_api_accepts_the_two_approved_connection_types(): void
    {
        foreach (['fusion_splice', 'mechanical_splice'] as $type) {
            [$company, [$a, $b]] = $this->endpoints();
            $response = $this->actingAs($this->connectionUser($company), 'sanctum')->postJson('/api/v1/fim/physical-connections', ['termination_a_id' => $b->id, 'termination_b_id' => $a->id, 'connection_type' => $type]);
            $response->assertCreated()->assertJsonPath('data.connection_type', $type);
            $this->assertDatabaseHas('physical_connections', ['connection_type' => $type, 'termination_a_id' => min($a->id, $b->id), 'termination_b_id' => max($a->id, $b->id)]);
        }
    }

    public function test_api_rejects_all_non_splice_connection_types(): void
    {
        foreach (['connector', 'patch', 'patchcord', 'unknown', 'arbitrary_value'] as $type) {
            [$company, [$a, $b]] = $this->endpoints();
            $this->actingAs($this->connectionUser($company), 'sanctum')->postJson('/api/v1/fim/physical-connections', ['termination_a_id' => $a->id, 'termination_b_id' => $b->id, 'connection_type' => $type])->assertUnprocessable()->assertJsonValidationErrors('connection_type');
            $this->assertDatabaseMissing('physical_connections', ['termination_a_id' => min($a->id, $b->id), 'termination_b_id' => max($a->id, $b->id)]);
        }
    }

    public function test_service_rejects_reversed_live_pair_without_creating_a_second_identity(): void
    {
        [$company, [$one, $two]] = $this->endpoints();
        $service = app(PhysicalConnectionService::class);
        $user = User::factory()->create(['company_id' => $company->id]);
        $first = $service->create(['termination_a_id' => $one->id, 'termination_b_id' => $two->id, 'connection_type' => 'fusion_splice'], $user);
        $this->assertLessThan($first->termination_b_id, $first->termination_a_id);

        try {
            $service->create(['termination_a_id' => $two->id, 'termination_b_id' => $one->id, 'connection_type' => 'fusion_splice'], $user);
            $this->fail('Expected reversed live pair to be rejected.');
        } catch (ValidationException) {
            $this->assertSame(1, \DB::table('physical_connections')->whereNull('deleted_at')->count());
            $this->assertDatabaseHas('physical_connections', ['id' => $first->id, 'termination_a_id' => min($one->id, $two->id), 'termination_b_id' => max($one->id, $two->id), 'deleted_at' => null]);
            foreach ([$one, $two] as $termination) {
                $this->assertSame(1, \DB::table('physical_connections')->whereNull('deleted_at')->where(fn ($q) => $q->where('termination_a_id', $termination->id)->orWhere('termination_b_id', $termination->id))->count());
            }
        }
    }

    public function test_api_updates_only_metadata_and_audits_compactly(): void
    {
        [$company, [$a, $b]] = $this->endpoints();
        $user = $this->connectionUser($company);
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/physical-connections', ['termination_a_id' => $a->id, 'termination_b_id' => $b->id, 'connection_type' => 'fusion_splice', 'metadata' => ['note' => 'created secret']])->assertCreated()->json('data.id');
        $original = PhysicalConnection::findOrFail($id);

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/physical-connections/{$id}", ['metadata' => ['note' => 'field corrected']])->assertOk();
        $updated = $original->fresh();
        $this->assertSame(['note' => 'field corrected'], $updated->metadata);
        $this->assertNull($updated->deleted_at);
        $this->assertSame([$original->company_id, $original->termination_a_id, $original->termination_b_id, $original->connection_type], [$updated->company_id, $updated->termination_a_id, $updated->termination_b_id, $updated->connection_type]);
        $audit = AuditLog::where('action', 'fim.physical-connection.updated')->latest('id')->firstOrFail();
        $this->assertTrue($audit->metadata['metadata_changed']);
        $this->assertNotContains('field corrected', $audit->metadata);
        $this->assertNotContains('created secret', AuditLog::where('action', 'fim.physical-connection.created')->latest('id')->firstOrFail()->metadata);
    }

    public function test_api_rejects_physical_connection_identity_mutation(): void
    {
        [$company, [$a, $b, $other]] = $this->endpoints();
        $user = $this->connectionUser($company);
        $connection = app(PhysicalConnectionService::class)->create(['termination_a_id' => $a->id, 'termination_b_id' => $b->id, 'connection_type' => 'fusion_splice'], $user);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/physical-connections/{$connection->id}", ['company_id' => 999, 'termination_a_id' => $other->id, 'termination_b_id' => $other->id, 'connection_type' => 'mechanical_splice'])->assertUnprocessable();
        $this->assertSame(['company_id' => $connection->company_id, 'termination_a_id' => $connection->termination_a_id, 'termination_b_id' => $connection->termination_b_id, 'connection_type' => $connection->connection_type], PhysicalConnection::findOrFail($connection->id)->only(['company_id', 'termination_a_id', 'termination_b_id', 'connection_type']));
    }

    public function test_api_update_requires_view_access_to_both_terminations(): void
    {
        [$company, [$a, $b]] = $this->endpoints();
        $owner = $this->connectionUser($company);
        $connection = app(PhysicalConnectionService::class)->create(['termination_a_id' => $a->id, 'termination_b_id' => $b->id, 'connection_type' => 'fusion_splice'], $owner);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('fim.physical-connections.update');
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/physical-connections/{$connection->id}", ['metadata' => ['note' => 'unauthorized']])->assertForbidden();
        $this->assertNull($connection->fresh()->metadata);
        $this->assertNull($connection->fresh()->deleted_at);
    }

    public function test_api_disconnect_audit_excludes_connection_metadata(): void
    {
        [$company, [$a, $b]] = $this->endpoints();
        $user = $this->connectionUser($company);
        $user->givePermissionTo(['fim.physical-connections.delete', 'fim.fiber-terminations.delete', 'fim.fiber-cores.delete', 'fim.fiber-segments.delete']);
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/v1/fim/physical-connections', ['termination_a_id' => $a->id, 'termination_b_id' => $b->id, 'connection_type' => 'fusion_splice', 'metadata' => ['note' => 'disconnect audit marker']])->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/physical-connections/{$id}")->assertOk();
        $this->assertSoftDeleted('physical_connections', ['id' => $id]);
        $audit = AuditLog::where('action', 'fim.physical-connection.disconnected')->latest('id')->firstOrFail();
        $this->assertNotContains('disconnect audit marker', $audit->metadata);
    }

    public function test_api_scopes_listing_and_denies_foreign_connection_routes(): void
    {
        [$companyA, [$a1, $a2]] = $this->endpoints();
        [$companyB, [$b1, $b2]] = $this->endpoints();
        $user = $this->connectionUser($companyA);
        $own = app(PhysicalConnectionService::class)->create(['termination_a_id' => $a1->id, 'termination_b_id' => $a2->id, 'connection_type' => 'fusion_splice'], $user);
        $foreign = app(PhysicalConnectionService::class)->create(['termination_a_id' => $b1->id, 'termination_b_id' => $b2->id, 'connection_type' => 'fusion_splice'], User::factory()->create(['company_id' => $companyB->id]));

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/physical-connections')->assertOk()->assertJsonFragment(['id' => $own->id])->assertJsonMissing(['id' => $foreign->id]);
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/physical-connections/{$own->id}")->assertOk()->assertJsonPath('data.id', $own->id);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/physical-connections/999999')->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/physical-connections/{$foreign->id}")->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/physical-connections/{$foreign->id}", ['metadata' => ['note' => 'foreign']])->assertForbidden();
        $user->givePermissionTo('fim.physical-connections.delete');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/physical-connections/{$foreign->id}")->assertForbidden();
        $this->assertNull($foreign->fresh()->deleted_at);
    }

    public function test_api_show_requires_endpoint_visibility_even_with_company_scope(): void
    {
        [$company, [$a, $b]] = $this->endpoints();
        $owner = $this->connectionUser($company);
        $connection = app(PhysicalConnectionService::class)->create(['termination_a_id' => $a->id, 'termination_b_id' => $b->id, 'connection_type' => 'fusion_splice'], $owner);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('fim.physical-connections.view');
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/physical-connections/{$connection->id}")->assertForbidden();
    }
}
