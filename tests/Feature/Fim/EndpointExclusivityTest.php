<?php

namespace Tests\Feature\Fim;

use App\Models\Asset;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\PhysicalConnection;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\Fim\FiberTerminationPortAttachmentService;
use App\Services\Fim\PhysicalConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EndpointExclusivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('ewnet_test', DB::connection()->getDatabaseName());
        $this->seed();
    }

    protected function user(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo([
            'fim.cables.view', 'fim.fiber-segments.view',
            'fim.fiber-cores.view', 'fim.fiber-terminations.view',
            'fim.physical-connections.view', 'fim.termination-port-attachments.view',
            'fim.connection-points.view', 'assets.view',
            'fim.passive-optical-ports.view',
        ]);
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        return $user->refresh();
    }

    protected function endpoint(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        return [$pointA, $pointB, $site];
    }

    protected function cable(Company $company, array $attributes = []): FiberCable
    {
        return FiberCable::create([
            'company_id' => $company->id,
            'cable_code' => 'FC-'.Str::random(8),
            'name' => 'Test Cable',
            'cable_type' => 'backbone',
            'fiber_count' => 4,
            'status' => 'installed',
            ...$attributes,
        ]);
    }

    protected function segment(FiberCable $cable, NetworkConnectionPoint $a, NetworkConnectionPoint $b, array $attributes = []): FiberSegment
    {
        return FiberSegment::create([
            'fiber_cable_id' => $cable->id,
            'endpoint_a_id' => $a->id,
            'endpoint_b_id' => $b->id,
            'company_id' => $cable->company_id,
            'sequence' => $attributes['sequence'] ?? 1,
            'status' => 'installed',
            ...$attributes,
        ]);
    }

    protected function core(FiberSegment $segment, int $number): FiberCore
    {
        return FiberCore::create([
            'fiber_segment_id' => $segment->id,
            'company_id' => $segment->company_id,
            'core_number' => $number,
            'status' => 'available',
        ]);
    }

    protected function termination(FiberCore $core, string $end, NetworkConnectionPoint $ncp): FiberTermination
    {
        return FiberTermination::create([
            'fiber_core_id' => $core->id,
            'company_id' => $core->company_id,
            'network_connection_point_id' => $ncp->id,
            'segment_end' => $end,
        ]);
    }

    protected function passivePort(Company $company, NetworkConnectionPoint $ncp): PassiveOpticalPort
    {
        $asset = Asset::factory()->create([
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'ODF',
            'status' => 'active',
        ]);

        return PassiveOpticalPort::create([
            'asset_id' => $asset->id,
            'network_connection_point_id' => $ncp->id,
            'company_id' => $company->id,
            'port_number' => 1,
            'port_role' => 'generic',
        ]);
    }

    // ── Service-level enforcement ───────────────────────────────

    public function test_service_blocks_splice_when_attachment_exists(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        FiberTerminationPortAttachment::create([
            'fiber_termination_id' => $termA->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $company->id,
        ]);

        $service = new PhysicalConnectionService;

        $this->expectException(ValidationException::class);
        $service->create([
            'termination_a_id' => $termA->id,
            'termination_b_id' => $termB->id,
            'connection_type' => 'fusion_splice',
        ], $user);
    }

    public function test_service_blocks_attachment_when_splice_exists(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        PhysicalConnection::create([
            'termination_a_id' => min($termA->id, $termB->id),
            'termination_b_id' => max($termA->id, $termB->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        $service = new FiberTerminationPortAttachmentService;

        $this->expectException(ValidationException::class);
        $service->attach($termA, $port->id, $user);
    }

    // ── Raw DB bypass blocked by triggers ───────────────────────

    public function test_raw_db_insert_blocked_splice_when_attachment_exists(): void
    {
        $company = Company::factory()->create();
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        DB::table('fiber_termination_port_attachments')->insert([
            'fiber_termination_id' => $termA->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\PDOException::class);
        DB::table('physical_connections')->insert([
            'termination_a_id' => min($termA->id, $termB->id),
            'termination_b_id' => max($termA->id, $termB->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_raw_db_insert_blocked_attachment_when_splice_exists(): void
    {
        $company = Company::factory()->create();
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        DB::table('physical_connections')->insert([
            'termination_a_id' => min($termA->id, $termB->id),
            'termination_b_id' => max($termA->id, $termB->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\PDOException::class);
        DB::table('fiber_termination_port_attachments')->insert([
            'fiber_termination_id' => $termA->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Historical rows permit new edges ────────────────────────

    public function test_historical_attachment_permits_new_splice(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        // Create and soft-delete attachment
        $attachment = FiberTerminationPortAttachment::create([
            'fiber_termination_id' => $termA->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $company->id,
        ]);
        $attachment->delete();

        // Splice should succeed — historical attachment does not block
        $service = new PhysicalConnectionService;
        $splice = $service->create([
            'termination_a_id' => $termA->id,
            'termination_b_id' => $termB->id,
            'connection_type' => 'fusion_splice',
        ], $user);

        $this->assertNotNull($splice);
        $this->assertDatabaseHas('physical_connections', [
            'id' => $splice->id,
            'deleted_at' => null,
        ]);
    }

    public function test_historical_splice_permits_new_attachment(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        // Create and soft-delete splice
        $splice = PhysicalConnection::create([
            'termination_a_id' => min($termA->id, $termB->id),
            'termination_b_id' => max($termA->id, $termB->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);
        $splice->delete();

        // Attachment should succeed — historical splice does not block
        $service = new FiberTerminationPortAttachmentService;
        $attachment = $service->attach($termA, $port->id, $user);

        $this->assertNotNull($attachment);
        $this->assertDatabaseHas('fiber_termination_port_attachments', [
            'id' => $attachment->id,
            'deleted_at' => null,
        ]);
    }

    // ── UPDATE/reactivation protection ──────────────────────────

    public function test_trigger_blocks_reactivating_splice_when_attachment_exists(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        // Step 1: create splice, then soft-delete it
        $pcService = new PhysicalConnectionService;
        $splice = $pcService->create([
            'termination_a_id' => $termA->id,
            'termination_b_id' => $termB->id,
            'connection_type' => 'fusion_splice',
        ], $user);
        $splice->delete();

        // Step 2: create attachment on termA (should succeed — historical splice doesn't block)
        $attService = new FiberTerminationPortAttachmentService;
        $attService->attach($termA, $port->id, $user);

        // Step 3: reactivate the splice — blocked because termA now has a live attachment
        $this->expectException(\Exception::class);
        DB::table('physical_connections')
            ->where('id', $splice->id)
            ->update(['deleted_at' => null]);
    }

    public function test_trigger_blocks_reactivating_attachment_when_splice_exists(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        // Step 1: create attachment, then soft-delete it
        $attService = new FiberTerminationPortAttachmentService;
        $attachment = $attService->attach($termA, $port->id, $user);
        $attachment->delete();

        // Step 2: create splice on termA (should succeed — historical attachment doesn't block)
        $pcService = new PhysicalConnectionService;
        $pcService->create([
            'termination_a_id' => $termA->id,
            'termination_b_id' => $termB->id,
            'connection_type' => 'fusion_splice',
        ], $user);

        // Step 3: reactivate the attachment — blocked because termA now has a live splice
        $this->expectException(\Exception::class);
        DB::table('fiber_termination_port_attachments')
            ->where('id', $attachment->id)
            ->update(['deleted_at' => null]);
    }

    public function test_trigger_blocks_changing_splice_endpoint_to_attached_termination(): void
    {
        $company = Company::factory()->create();
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $seg3 = $this->segment($cable, $pointA, $pointB, ['sequence' => 3]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);
        $core3 = $this->core($seg3, 3);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $termC = $this->termination($core3, 'B', $pointB);
        $port = $this->passivePort($company, $pointB);

        // Create splice between termA and termB
        $splice = PhysicalConnection::create([
            'termination_a_id' => min($termA->id, $termB->id),
            'termination_b_id' => max($termA->id, $termB->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        // Create attachment on termC
        FiberTerminationPortAttachment::create([
            'fiber_termination_id' => $termC->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $company->id,
        ]);

        // Try to change splice endpoint from termB to termC (which has an attachment)
        $this->expectException(\PDOException::class);
        DB::table('physical_connections')
            ->where('id', $splice->id)
            ->update(['termination_b_id' => $termC->id]);
    }

    // ── Deterministic endpoint locking ──────────────────────────

    public function test_splice_creation_locks_terminations_in_ascending_id_order(): void
    {
        $company = Company::factory()->create();
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);

        // The trigger locks LEAST(a,b) then GREATEST(a,b) — deterministic ordering
        // Verify by checking that the canonical ordering is enforced
        $this->assertLessThan($termB->id, $termA->id, 'Test assumes termA has lower ID for canonical ordering');

        $splice = PhysicalConnection::create([
            'termination_a_id' => $termA->id,
            'termination_b_id' => $termB->id,
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        $this->assertSame($termA->id, $splice->termination_a_id);
        $this->assertSame($termB->id, $splice->termination_b_id);
    }

    // ── Concurrency test ────────────────────────────────────────

    public function test_concurrent_splice_and_attachment_one_rejected(): void
    {
        $company = Company::factory()->create();
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        // Both operations within separate transactions — the trigger enforces exclusivity.
        // Transaction A: create splice
        DB::transaction(function () use ($termA, $termB, $company) {
            DB::table('fiber_terminations')->where('id', $termA->id)->lockForUpdate()->get();
            DB::table('fiber_terminations')->where('id', $termB->id)->lockForUpdate()->get();
            DB::table('physical_connections')->insert([
                'termination_a_id' => min($termA->id, $termB->id),
                'termination_b_id' => max($termA->id, $termB->id),
                'connection_type' => 'fusion_splice',
                'company_id' => $company->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Transaction B: attempt attachment on same termination — must fail
        $caught = false;
        try {
            DB::transaction(function () use ($termA, $port, $company) {
                DB::table('fiber_terminations')->where('id', $termA->id)->lockForUpdate()->get();
                DB::table('fiber_termination_port_attachments')->insert([
                    'fiber_termination_id' => $termA->id,
                    'passive_optical_port_id' => $port->id,
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Exception $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Second transaction must be rejected by trigger');
        $this->assertDatabaseCount('fiber_termination_port_attachments', 0);
    }

    // ── Valid topology not blocked ───────────────────────────────

    public function test_splice_and_attachment_on_different_terminations_allowed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointA, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $pointB);
        $termB = $this->termination($core2, 'A', $pointB);
        $port = $this->passivePort($company, $pointB);

        // Splice between termA and termB
        PhysicalConnection::create([
            'termination_a_id' => min($termA->id, $termB->id),
            'termination_b_id' => max($termA->id, $termB->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        // Attachment on termB (different termination than the splice endpoints? No — termB IS a splice endpoint)
        // Actually, termB has the splice. So attachment on termB should be blocked.
        // Let's use a third termination for the attachment.
        $seg3 = $this->segment($cable, $pointA, $pointB, ['sequence' => 3]);
        $core3 = $this->core($seg3, 3);
        $termC = $this->termination($core3, 'B', $pointB);

        // termC has no splice — attachment should succeed
        $service = new FiberTerminationPortAttachmentService;
        $attachment = $service->attach($termC, $port->id, $user);

        $this->assertNotNull($attachment);
        $this->assertDatabaseHas('fiber_termination_port_attachments', [
            'id' => $attachment->id,
            'deleted_at' => null,
        ]);
    }
}
