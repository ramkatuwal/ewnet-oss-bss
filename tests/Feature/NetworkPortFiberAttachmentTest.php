<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiberCore;
use App\Models\FiberTermination;
use App\Models\NetworkPort;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Services\Fim\FiberTerminationPortAttachmentService;
use App\Services\Fim\FiberTerminationService;
use App\Services\Fim\PhysicalConnectionService;
use App\Services\Fim\StrandContinuityService;
use App\Services\Network\NetworkPortService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NetworkPortFiberAttachmentTest extends TestCase
{
    use NetworkPortFiberFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('ewnet_test', DB::selectOne('select current_database() as db')->db);
        $this->seed();
    }

    public function test_api_disconnect_reconnect_identity_and_safe_audit_without_endpoint_mutation_permissions(): void
    {
        [$port, $term, $other, $passive, $user] = $this->fixture();
        $url = "/api/v1/network-ports/{$port->id}/fiber-attachments";
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($user);
        $first = $this->postJson($url, ['fiber_termination_id' => $term->id, 'metadata' => ['private_note' => 'not-for-audit']])
            ->assertCreated()->assertJsonPath('data.company_id', $port->company_id)->json('data.id');
        $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 1);
        $this->postJson($url, ['fiber_termination_id' => $term->id])->assertUnprocessable();
        $this->patchJson("/api/v1/network-port-fiber-attachments/{$first}", ['metadata' => []])->assertMethodNotAllowed();
        $this->deleteJson("/api/v1/network-port-fiber-attachments/{$first}")->assertOk();
        $this->getJson($url)->assertJsonPath('meta.total', 0);
        $second = $this->postJson($url, ['fiber_termination_id' => $term->id])->assertCreated()->json('data.id');
        $this->assertNotSame($first, $second);
        $this->assertSoftDeleted('network_port_fiber_termination_attachments', ['id' => $first]);
        $this->assertSame($user->id, NetworkPortFiberTerminationAttachment::withTrashed()->find($first)->updated_by);
        $logs = AuditLog::where('action', 'like', 'net.network-port-fiber-attachment.%')->get();
        $this->assertCount(3, $logs);
        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->actor_id);
            $this->assertSame(['id', 'network_port_id', 'fiber_termination_id', 'company_id'], array_keys($log->metadata));
        }
        $this->assertDatabaseCount('physical_connections', 0);
        $this->assertDatabaseCount('fiber_termination_port_attachments', 0);
        $this->assertNull($term->networkConnectionPoint->network_port_id);
    }

    public function test_collection_filters_hidden_termination_before_count_and_serialization(): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $attachment = $this->attach($port, $term, $user);
        $user->revokePermissionTo('fim.fiber-terminations.view');
        $this->actingAs($user)->getJson("/api/v1/network-ports/{$port->id}/fiber-attachments?per_page=1")
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0)->assertJsonMissing(['id' => $attachment->id]);
        $this->postJson("/api/v1/network-ports/{$port->id}/fiber-attachments", ['fiber_termination_id' => $term->id])->assertForbidden();
        $this->deleteJson("/api/v1/network-port-fiber-attachments/{$attachment->id}")->assertForbidden();
    }

    #[DataProvider('missingPermissions')]
    public function test_both_endpoint_visibility_and_operation_permission_required(string $permission): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $user->revokePermissionTo($permission);
        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/fiber-attachments", ['fiber_termination_id' => $term->id])->assertForbidden();
    }

    public static function missingPermissions(): array
    {
        return array_map(fn ($p) => [$p], ['assets.view', 'net.network-ports.view', 'fim.fiber-terminations.view', 'fim.fiber-cores.view', 'fim.fiber-segments.view', 'fim.connection-points.view', 'net.network-port-fiber-attachments.create']);
    }

    public function test_company_and_branch_scope_cannot_bypass_endpoint_visibility(): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $attachment = $this->attach($port, $term, $user);
        $foreign = Company::factory()->create();
        $user->managementScopes()->update(['scope_id' => $foreign->id]);
        $user->unsetRelation('managementScopes');
        $this->actingAs($user)->getJson("/api/v1/network-ports/{$port->id}/fiber-attachments")->assertForbidden();
        $this->postJson("/api/v1/network-ports/{$port->id}/fiber-attachments", ['fiber_termination_id' => $term->id])->assertForbidden();
        $this->deleteJson("/api/v1/network-port-fiber-attachments/{$attachment->id}")->assertForbidden();
        $user->managementScopes()->update(['scope_type' => 'branch', 'scope_id' => $port->asset->site->branch_id ?? 1]);
        $user->unsetRelation('managementScopes');
        $this->getJson("/api/v1/network-ports/{$port->id}/fiber-attachments")->assertForbidden();
    }

    public function test_request_rejects_identity_actor_injection_and_invalid_pagination(): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $url = "/api/v1/network-ports/{$port->id}/fiber-attachments";
        $this->actingAs($user)->postJson($url, ['fiber_termination_id' => $term->id, 'company_id' => $port->company_id])->assertUnprocessable();
        $this->postJson($url, ['fiber_termination_id' => $term->id, 'created_by' => $user->id])->assertUnprocessable();
        $this->postJson($url, ['metadata' => 'invalid'])->assertUnprocessable();
        $this->getJson($url.'?per_page=0')->assertUnprocessable();
        $this->getJson($url.'?per_page=101')->assertUnprocessable();
        $this->getJson($url.'?page=-1')->assertUnprocessable();
    }

    #[DataProvider('invalidEndpoints')]
    public function test_service_and_raw_database_reject_invalid_endpoints(string $invalid): void
    {
        [$port, $term, , , $user] = $this->fixture();
        match ($invalid) {
            'deleted_port' => $port->delete(),
            'deleted_termination' => $term->delete(),
            'wrong_company' => $port->update(['company_id' => Company::factory()->create()->id]),
            'non_network_parent' => $port->update(['asset_id' => Asset::factory()->create(['category' => 'INFRASTRUCTURE', 'type' => 'ODF', 'company_id' => $port->company_id])->id]),
            'deleted_parent' => $port->update(['asset_id' => $this->deletedAsset($port->company_id)->id]),
            'parent_company' => $port->update(['asset_id' => Asset::factory()->create(['category' => 'NETWORK', 'type' => 'OLT'])->id]),
            'explicit_ncp_contradiction' => $term->networkConnectionPoint->update(['network_port_id' => NetworkPort::factory()->create(['asset_id' => $port->asset_id, 'company_id' => $port->company_id])->id]),
        };
        $this->rejects(fn () => $this->attach($port, $term, $user), ValidationException::class);
        $this->rejects(fn () => DB::table('network_port_fiber_termination_attachments')->insert($this->activeRow($port, $term)), QueryException::class);
    }

    private function deletedAsset(int $companyId): Asset
    {
        $asset = Asset::factory()->create(['category' => 'NETWORK', 'type' => 'OLT', 'company_id' => $companyId]);
        $asset->delete();

        return $asset;
    }

    public static function invalidEndpoints(): array
    {
        return array_map(fn ($v) => [$v], ['deleted_port', 'deleted_termination', 'wrong_company', 'non_network_parent', 'deleted_parent', 'parent_company', 'explicit_ncp_contradiction']);
    }

    public function test_optional_ncp_association_and_unrelated_weak_asset_are_not_required(): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $term->networkConnectionPoint->update(['asset_id' => Asset::factory()->create(['category' => 'INFRASTRUCTURE', 'type' => 'ODF', 'company_id' => $port->company_id])->id]);
        $first = $this->attach($port, $term, $user);
        $first->delete();
        $term->networkConnectionPoint->update(['network_port_id' => $port->id]);
        $this->assertNotSame($first->id, $this->attach($port, $term, $user)->id);
    }

    #[DataProvider('competitors')]
    public function test_competing_directions_services_raw_db_and_soft_delete_release(string $kind): void
    {
        [$port, $term, $other, $passive, $user] = $this->fixture();
        $active = $this->attach($port, $term, $user);
        $service = fn () => $kind === 'passive'
            ? app(FiberTerminationPortAttachmentService::class)->attach($term, $passive->id, $user)
            : app(PhysicalConnectionService::class)->create(['termination_a_id' => $term->id, 'termination_b_id' => $other->id, 'connection_type' => 'fusion_splice'], $user);
        [$table, $row] = $this->competitorRow($kind, $term, $other, $passive);
        $this->rejects($service, ValidationException::class);
        $this->rejects(fn () => DB::table($table)->insert($row), QueryException::class);
        $active->delete();
        $competitor = $service();
        $this->rejects(fn () => $this->attach($port, $term, $user), ValidationException::class);
        $this->rejects(fn () => DB::table('network_port_fiber_termination_attachments')->insert($this->activeRow($port, $term)), QueryException::class);
        $competitor->delete();
        $new = $this->attach($port, $term, $user);
        $this->assertNotSame($new->id, $active->id);
        $this->rejects(fn () => DB::table($table)->where('id', $competitor->id)->update(['deleted_at' => null]), QueryException::class);
    }

    public static function competitors(): array
    {
        return [['passive'], ['splice']];
    }

    public function test_unique_each_endpoint_and_no_restore_or_hard_delete_of_attachment_history(): void
    {
        [$port, $term, $other, , $user] = $this->fixture();
        $active = $this->attach($port, $term, $user);
        $otherPort = NetworkPort::factory()->create(['asset_id' => $port->asset_id, 'company_id' => $port->company_id]);
        foreach ([[$port, $other], [$otherPort, $term], [$port, $term]] as [$p, $t]) {
            $this->rejects(fn () => $this->attach($p, $t, $user), ValidationException::class);
            $this->rejects(fn () => DB::table('network_port_fiber_termination_attachments')->insert($this->activeRow($p, $t)), QueryException::class);
        }
        $active->delete();
        $this->rejects(fn () => $active->restore(), QueryException::class);
        $this->rejects(fn () => $active->forceDelete(), QueryException::class);
        $this->assertNotSame($active->id, $this->attach($port, $other, $user)->id);
        $this->assertNotNull($this->attach($otherPort, $term, $user));
    }

    #[DataProvider('historyChanges')]
    public function test_live_and_disconnected_history_protect_endpoints_and_attachment_identity(string $target, string $field, bool $historical): void
    {
        [$port, $term, $other, , $user] = $this->fixture();
        $attachment = $this->attach($port, $term, $user);
        if ($historical) {
            $attachment->delete();
        }
        $model = match ($target) {
            'port' => $port, 'term' => $term, 'attachment' => $attachment, 'asset' => $port->asset
        };
        $this->rejects(function () use ($model, $field, $other) {
            if ($field === 'hard_delete') {
                DB::table($model->getTable())->where('id', $model->id)->delete();
            } else {
                $value = match ($field) {
                    'deleted_at' => now(), 'port_key' => 'changed', 'segment_end' => 'B', 'category' => 'INFRASTRUCTURE',
                    'fiber_core_id' => $other->fiber_core_id, 'fiber_termination_id' => $other->id,
                    default => 99999999,
                };
                DB::table($model->getTable())->where('id', $model->id)->update([$field => $value]);
            }
        }, QueryException::class);
    }

    public static function historyChanges(): array
    {
        $cases = [];
        foreach (['port' => ['hard_delete', 'deleted_at', 'asset_id', 'company_id', 'port_key'], 'term' => ['hard_delete', 'deleted_at', 'fiber_core_id', 'company_id', 'segment_end', 'network_connection_point_id'], 'attachment' => ['network_port_id', 'fiber_termination_id', 'company_id'], 'asset' => ['hard_delete', 'deleted_at', 'category', 'company_id']] as $target => $fields) {
            foreach ($fields as $field) {
                foreach ([false, true] as $history) {
                    $cases["{$target}-{$field}-".(int) $history] = [$target, $field, $history];
                }
            }
        }

        return $cases;
    }

    public function test_service_history_guards_and_descriptive_updates_remain_available(): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $this->attach($port, $term, $user)->delete();
        $this->rejects(fn () => app(NetworkPortService::class)->delete($port), ValidationException::class);
        $this->rejects(fn () => app(FiberTerminationService::class)->delete($term), ValidationException::class);
        $this->rejects(fn () => app(NetworkPortService::class)->update($port, ['port_key' => 'changed'], $user), ValidationException::class);
        $this->rejects(fn () => app(FiberTerminationService::class)->update($term, ['segment_end' => 'B'], $user), ValidationException::class);
        $this->assertSame('renamed', app(NetworkPortService::class)->update($port, ['name' => 'renamed'], $user)->name);
        $this->assertSame(['note' => 'ok'], app(FiberTerminationService::class)->update($term, ['metadata' => ['note' => 'ok']], $user)->metadata);
    }

    public function test_raw_updates_cannot_move_competing_edges_onto_an_active_network_termination(): void
    {
        [$port, $term, $other, $passive, $user] = $this->fixture();
        $this->attach($port, $term, $user);
        $attachment = app(FiberTerminationPortAttachmentService::class)->attach($other, $passive->id, $user);
        $this->rejects(fn () => DB::table('fiber_termination_port_attachments')->where('id', $attachment->id)->update(['fiber_termination_id' => $term->id]), QueryException::class);
        $attachment->delete();
        $core = FiberCore::create(['company_id' => $term->company_id, 'fiber_segment_id' => $term->fiberCore->fiber_segment_id, 'core_number' => 3, 'status' => 'available']);
        $third = FiberTermination::create(['company_id' => $term->company_id, 'fiber_core_id' => $core->id, 'network_connection_point_id' => $term->network_connection_point_id, 'segment_end' => 'A']);
        $splice = app(PhysicalConnectionService::class)->create(['termination_a_id' => $other->id, 'termination_b_id' => $third->id, 'connection_type' => 'fusion_splice'], $user);
        $this->rejects(fn () => DB::table('physical_connections')->where('id', $splice->id)->update(['termination_a_id' => $term->id]), QueryException::class);
    }

    public function test_ncp_cannot_become_authoritatively_contradictory_after_disconnect(): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $this->attach($port, $term, $user)->delete();
        $otherPort = NetworkPort::factory()->create(['asset_id' => $port->asset_id, 'company_id' => $port->company_id]);
        $this->rejects(fn () => $term->networkConnectionPoint->update(['network_port_id' => $otherPort->id]), QueryException::class);
        $term->networkConnectionPoint->update(['network_port_id' => $port->id]);
        $term->networkConnectionPoint->update(['network_port_id' => null]);
        $this->assertNull($term->networkConnectionPoint->fresh()->network_port_id);
    }

    public function test_new_migration_alone_rolls_back_and_reapplies(): void
    {
        $migration = require database_path('migrations/2026_09_09_120000_create_network_port_fiber_termination_attachments.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('network_port_fiber_termination_attachments'));
        $this->assertTrue(Schema::hasTable('network_ports'));
        $migration->up();
        [$port, $term, , , $user] = $this->fixture();
        $this->assertNotNull($this->attach($port, $term, $user));
    }

    public function test_migration_preflight_fails_on_legacy_conflict_without_repair(): void
    {
        $migration = require database_path('migrations/2026_09_09_120000_create_network_port_fiber_termination_attachments.php');
        $migration->down();
        [$port, $term, $other, $passive] = $this->fixture();
        [$table, $row] = $this->competitorRow('splice', $term, $other, $passive);
        DB::table($table)->insert($row);
        // Simulate data predating exclusivity enforcement, only inside this rollback-isolated test.
        DB::statement('ALTER TABLE fiber_termination_port_attachments DISABLE TRIGGER fim_prevent_attachment_splice_conflict_trigger');
        [$table, $row] = $this->competitorRow('passive', $term, $other, $passive);
        DB::table($table)->insert($row);
        DB::statement('ALTER TABLE fiber_termination_port_attachments ENABLE TRIGGER fim_prevent_attachment_splice_conflict_trigger');
        try {
            $migration->up();
            $this->fail('Preflight should reject conflicting legacy edges.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('NED-004 preflight', $e->getMessage());
        }
        $this->assertFalse(Schema::hasTable('network_port_fiber_termination_attachments'));
        $this->assertDatabaseCount('physical_connections', 1);
        $this->assertDatabaseCount('fiber_termination_port_attachments', 1);
    }

    public function test_strand_terminal_redaction_modes_and_no_fim005_exposure_or_inferred_connectivity(): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $service = app(StrandContinuityService::class);
        $before = $this->actingAs($user)->getJson("/api/v1/fim/topology/terminations/{$term->id}")->assertOk()->json();
        $term->networkConnectionPoint->update(['network_port_id' => $port->id]);
        $this->assertSame('no_splice', $service->traceFromCore($term->fiberCore, $user)->terminalA['type']);
        $this->attach($port, $term, $user);
        foreach (['physical-strand', 'same-cable-strand'] as $mode) {
            $path = $service->traceFromCore($term->fiberCore, $user, $mode);
            $this->assertSame(['type' => 'active_network_port', 'id' => $port->id, 'data' => null], $path->terminalA);
            $this->assertSame([], $path->connections);
            $this->assertSame(0, $path->depth);
            $this->assertFalse($path->cycleDetected);
            $this->assertFalse($path->truncated);
        }
        $after = $this->getJson("/api/v1/fim/topology/terminations/{$term->id}")->assertOk()->json();
        $this->assertSame($before, $after);
        $user->revokePermissionTo('net.network-port-fiber-attachments.view');
        $this->assertSame(['type' => 'scope_boundary', 'id' => null, 'data' => null], $service->traceFromCore($term->fiberCore, $user)->terminalA);
        $user->givePermissionTo('net.network-port-fiber-attachments.view');
        $user->revokePermissionTo('net.network-ports.view');
        $this->assertSame(['type' => 'scope_boundary', 'id' => null, 'data' => null], $service->traceFromCore($term->fiberCore, $user)->terminalA);
    }

    private function rejects(callable $operation, string $exception): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected '.$exception);
        } catch (QueryException|ValidationException $e) {
            $this->assertInstanceOf($exception, $e);
        }
    }
}
