<?php

namespace Tests\Feature\Fim;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiberTerminationPortAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array{0: FiberTermination, 1: PassiveOpticalPort, 2: PassiveOpticalPort} */
    protected function endpoint(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'ODF']);
        $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
        $otherEndpoint = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $segment = FiberSegment::factory()->create(['fiber_cable_id' => $cable->id, 'company_id' => $company->id, 'endpoint_a_id' => $point->id, 'endpoint_b_id' => $otherEndpoint->id]);
        $core = FiberCore::factory()->create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id]);
        $termination = FiberTermination::create(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $point->id, 'segment_end' => 'A']);
        $firstPort = PassiveOpticalPort::factory()->create(['asset_id' => $asset->id, 'network_connection_point_id' => $point->id, 'company_id' => $company->id, 'port_number' => 'P-1']);
        $secondPort = PassiveOpticalPort::factory()->create(['asset_id' => $asset->id, 'network_connection_point_id' => $point->id, 'company_id' => $company->id, 'port_number' => 'P-2']);

        return [$termination, $firstPort, $secondPort];
    }

    protected function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);

        return $user->refresh();
    }

    protected function permissions(string ...$attachment): array
    {
        return [...$attachment, 'fim.fiber-terminations.view', 'fim.fiber-cores.view', 'fim.fiber-segments.view', 'fim.cables.view', 'fim.connection-points.view', 'fim.passive-optical-ports.view', 'assets.view'];
    }

    protected function insertAttachment(FiberTermination $termination, PassiveOpticalPort $port, Company $company): void
    {
        \DB::table('fiber_termination_port_attachments')->insert([
            'fiber_termination_id' => $termination->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function assertAttachmentRejected(FiberTermination $termination, PassiveOpticalPort $port, Company $company): void
    {
        try {
            \DB::transaction(fn () => $this->insertAttachment($termination, $port, $company));
            $this->fail('Expected PostgreSQL to reject the attachment.');
        } catch (QueryException) {
            // The nested transaction rolls back PostgreSQL's failed statement savepoint.
        }
    }

    public function test_attach_detach_and_reattach_preserve_history_without_parent_update_permissions(): void
    {
        $company = Company::factory()->create();
        [$termination, $firstPort, $secondPort] = $this->endpoint($company);
        $user = $this->user($company, $this->permissions('fim.termination-port-attachments.view', 'fim.termination-port-attachments.create', 'fim.termination-port-attachments.delete'));
        $endpoint = "/api/v1/fim/fiber-terminations/{$termination->id}/port-attachments";

        $created = $this->actingAs($user, 'sanctum')->postJson($endpoint, [
            'passive_optical_port_id' => $firstPort->id,
            'metadata' => ['marker' => 'attachment create audit marker'],
        ]);
        $created->assertCreated()
            ->assertJsonPath('data.fiber_termination_id', $termination->id)
            ->assertJsonPath('data.passive_optical_port_id', $firstPort->id)
            ->assertJsonPath('data.metadata.marker', 'attachment create audit marker');
        $attachmentId = $created->json('data.id');
        $this->assertDatabaseHas('fiber_termination_port_attachments', ['id' => $attachmentId, 'metadata->marker' => 'attachment create audit marker']);
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/termination-port-attachments/{$attachmentId}", [
            'metadata' => ['marker' => 'attachment detach audit marker'],
        ])->assertOk();
        $this->assertSoftDeleted('fiber_termination_port_attachments', ['id' => $attachmentId]);
        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $secondPort->id])->assertCreated();
        $this->assertDatabaseCount('fiber_termination_port_attachments', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.termination-port-attachment.created', 'target_id' => $attachmentId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.termination-port-attachment.deleted', 'target_id' => $attachmentId]);
        $this->assertNotContains('attachment create audit marker', AuditLog::where('action', 'fim.termination-port-attachment.created')->where('target_id', $attachmentId)->firstOrFail()->metadata);
        $this->assertNotContains('attachment detach audit marker', AuditLog::where('action', 'fim.termination-port-attachment.deleted')->where('target_id', $attachmentId)->firstOrFail()->metadata);
    }

    public function test_api_rejects_cross_company_different_ncp_and_live_duplicate_attachments(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);
        $other = Company::factory()->create();
        [, $foreignPort] = $this->endpoint($other);
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'ODF']);
        $otherPoint = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
        $wrongPointPort = PassiveOpticalPort::factory()->create(['asset_id' => $asset->id, 'network_connection_point_id' => $otherPoint->id, 'company_id' => $company->id]);
        $user = $this->user($company, $this->permissions('fim.termination-port-attachments.create'));
        $endpoint = "/api/v1/fim/fiber-terminations/{$termination->id}/port-attachments";

        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $foreignPort->id])->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $wrongPointPort->id])->assertUnprocessable()->assertJsonValidationErrors('passive_optical_port_id');
        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $port->id])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $port->id])->assertUnprocessable();
    }

    public function test_database_accepts_same_company_same_ncp_attachment(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);

        $this->insertAttachment($termination, $port, $company);

        $this->assertDatabaseHas('fiber_termination_port_attachments', ['fiber_termination_id' => $termination->id, 'passive_optical_port_id' => $port->id, 'company_id' => $company->id, 'deleted_at' => null]);
    }

    public function test_database_rejects_different_company_or_connection_point(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);
        $other = Company::factory()->create();

        $this->assertAttachmentRejected($termination, $port, $other);
        $this->assertDatabaseCount('fiber_termination_port_attachments', 0);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'ODF']);
        $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
        $wrongPointPort = PassiveOpticalPort::factory()->create(['asset_id' => $asset->id, 'network_connection_point_id' => $point->id, 'company_id' => $company->id]);

        $this->assertAttachmentRejected($termination, $wrongPointPort, $company);
    }

    public function test_database_enforces_one_to_one_live_cardinality(): void
    {
        $company = Company::factory()->create();
        [$termination, $port, $secondPort] = $this->endpoint($company);
        $this->insertAttachment($termination, $port, $company);

        $this->assertAttachmentRejected($termination, $secondPort, $company);
        $this->assertDatabaseCount('fiber_termination_port_attachments', 1);

        $secondCore = FiberCore::factory()->create([
            'fiber_segment_id' => $termination->fiberCore->fiber_segment_id,
            'company_id' => $company->id,
            'core_number' => $termination->fiberCore->core_number + 1,
        ]);
        $secondTermination = FiberTermination::create(['fiber_core_id' => $secondCore->id, 'company_id' => $company->id, 'network_connection_point_id' => $termination->network_connection_point_id, 'segment_end' => 'A']);

        $this->assertAttachmentRejected($secondTermination, $port, $company);
        $this->assertDatabaseCount('fiber_termination_port_attachments', 1);
    }

    public function test_database_prevents_termination_and_port_soft_delete_after_attachment_history(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);
        $this->insertAttachment($termination, $port, $company);
        \DB::table('fiber_termination_port_attachments')->where('fiber_termination_id', $termination->id)->update(['deleted_at' => now()]);

        try {
            \DB::transaction(fn () => \DB::table('fiber_terminations')->whereKey($termination->id)->update(['deleted_at' => now()]));
            $this->fail('Expected PostgreSQL to preserve termination attachment history.');
        } catch (QueryException) {
            $this->assertNull($termination->fresh()->deleted_at);
        }

        try {
            \DB::transaction(fn () => \DB::table('passive_optical_ports')->whereKey($port->id)->update(['deleted_at' => now()]));
            $this->fail('Expected PostgreSQL to preserve passive optical port attachment history.');
        } catch (QueryException) {
            $this->assertNull($port->fresh()->deleted_at);
        }
    }

    public function test_database_prevents_termination_and_port_soft_delete_with_live_attachment(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);
        $this->insertAttachment($termination, $port, $company);

        try {
            \DB::transaction(fn () => \DB::table('fiber_terminations')->whereKey($termination->id)->update(['deleted_at' => now()]));
            $this->fail('Expected PostgreSQL to preserve the live termination attachment.');
        } catch (QueryException) {
            $this->assertNull($termination->fresh()->deleted_at);
            $this->assertNull($port->fresh()->deleted_at);
            $this->assertDatabaseHas('fiber_termination_port_attachments', ['fiber_termination_id' => $termination->id, 'passive_optical_port_id' => $port->id, 'deleted_at' => null]);
        }

        try {
            \DB::transaction(fn () => \DB::table('passive_optical_ports')->whereKey($port->id)->update(['deleted_at' => now()]));
            $this->fail('Expected PostgreSQL to preserve the live passive optical port attachment.');
        } catch (QueryException) {
            $this->assertNull($termination->fresh()->deleted_at);
            $this->assertNull($port->fresh()->deleted_at);
            $this->assertDatabaseHas('fiber_termination_port_attachments', ['fiber_termination_id' => $termination->id, 'passive_optical_port_id' => $port->id, 'deleted_at' => null]);
        }
    }

    public function test_api_requires_visibility_to_both_endpoints_without_mutating_attachments(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);
        $endpoint = "/api/v1/fim/fiber-terminations/{$termination->id}/port-attachments";
        $withoutTerminationView = array_values(array_diff($this->permissions('fim.termination-port-attachments.create'), ['fim.fiber-terminations.view']));
        $withoutPortView = array_values(array_diff($this->permissions('fim.termination-port-attachments.create'), ['fim.passive-optical-ports.view']));

        $this->actingAs($this->user($company, $withoutTerminationView), 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $port->id])->assertForbidden();
        $this->actingAs($this->user($company, $withoutPortView), 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $port->id])->assertForbidden();
        $this->assertDatabaseCount('fiber_termination_port_attachments', 0);

        $owner = $this->user($company, $this->permissions('fim.termination-port-attachments.create', 'fim.termination-port-attachments.delete'));
        $attachmentId = $this->actingAs($owner, 'sanctum')->postJson($endpoint, ['passive_optical_port_id' => $port->id])->assertCreated()->json('data.id');
        $withoutTerminationView = array_values(array_diff($this->permissions('fim.termination-port-attachments.delete'), ['fim.fiber-terminations.view']));
        $withoutPortView = array_values(array_diff($this->permissions('fim.termination-port-attachments.delete'), ['fim.passive-optical-ports.view']));

        $this->actingAs($this->user($company, $withoutTerminationView), 'sanctum')->deleteJson("/api/v1/fim/termination-port-attachments/{$attachmentId}")->assertForbidden();
        $this->actingAs($this->user($company, $withoutPortView), 'sanctum')->deleteJson("/api/v1/fim/termination-port-attachments/{$attachmentId}")->assertForbidden();
        $this->assertDatabaseHas('fiber_termination_port_attachments', ['id' => $attachmentId, 'deleted_at' => null]);
        $this->assertNull($termination->fresh()->deleted_at);
        $this->assertNull($port->fresh()->deleted_at);
    }

    public function test_nested_attachment_collection_filters_invisible_ports_without_leaking_counts(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);
        $this->insertAttachment($termination, $port, $company);
        $endpoint = "/api/v1/fim/fiber-terminations/{$termination->id}/port-attachments";

        $visible = $this->user($company, $this->permissions('fim.termination-port-attachments.view'));
        $this->actingAs($visible, 'sanctum')->getJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.0.passive_optical_port_id', $port->id)
            ->assertJsonPath('meta.total', 1);

        $withoutPortView = array_values(array_diff($this->permissions('fim.termination-port-attachments.view'), ['fim.passive-optical-ports.view']));
        $this->actingAs($this->user($company, $withoutPortView), 'sanctum')->getJson($endpoint)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonMissing(['id' => 1])
            ->assertJsonMissing(['passive_optical_port_id' => $port->id]);
    }

    public function test_nested_attachment_collection_requires_termination_and_attachment_view_permissions(): void
    {
        $company = Company::factory()->create();
        [$termination, $port] = $this->endpoint($company);
        $this->insertAttachment($termination, $port, $company);
        $endpoint = "/api/v1/fim/fiber-terminations/{$termination->id}/port-attachments";

        $withoutTerminationView = array_values(array_diff($this->permissions('fim.termination-port-attachments.view'), ['fim.fiber-terminations.view']));
        $this->actingAs($this->user($company, $withoutTerminationView), 'sanctum')->getJson($endpoint)->assertForbidden();

        $this->actingAs($this->user($company, $this->permissions()), 'sanctum')->getJson($endpoint)->assertForbidden();
    }
}
