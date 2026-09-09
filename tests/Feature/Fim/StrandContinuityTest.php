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
use App\Services\Fim\StrandContinuityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StrandContinuityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('ewnet_test', DB::connection()->getDatabaseName());
        $this->seed();
    }

    protected function user(Company $company, ?array $permissions = null): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions ?? [
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

    protected function splice(FiberTermination $a, FiberTermination $b): PhysicalConnection
    {
        return PhysicalConnection::create([
            'company_id' => $a->company_id,
            'termination_a_id' => min($a->id, $b->id),
            'termination_b_id' => max($a->id, $b->id),
            'connection_type' => 'fusion_splice',
        ]);
    }

    protected function attachment(FiberTermination $termination, PassiveOpticalPort $port): FiberTerminationPortAttachment
    {
        return FiberTerminationPortAttachment::create([
            'fiber_termination_id' => $termination->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $termination->company_id,
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

    // ── 0 termination cases ──────────────────────────────────────

    public function test_single_core_no_terminations(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path");

        $response->assertOk()->assertJsonFragment([
            'fiber_core_id' => $core->id,
            'depth' => 0,
            'truncated' => false,
            'cycle_detected' => false,
            'cable_boundary_crossed' => false,
        ])->assertJsonPath('data.terminal_a.type', 'no_termination')
            ->assertJsonPath('data.terminal_b.type', 'no_termination')
            ->assertJsonCount(1, 'data.cores')
            ->assertJsonCount(0, 'data.connections');
    }

    // ── 1 termination (partial) ──────────────────────────────────

    public function test_single_core_one_termination_no_splice(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);
        $this->termination($core, 'A', $pointA);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path");

        $response->assertOk()
            ->assertJsonPath('data.terminal_a.type', 'no_splice')
            ->assertJsonPath('data.terminal_b.type', 'no_termination')
            ->assertJsonCount(1, 'data.cores')
            ->assertJsonCount(0, 'data.connections');
    }

    // ── 2-core splice ────────────────────────────────────────────

    public function test_two_cores_one_splice(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp, $pointB, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path");

        $response->assertOk()
            ->assertJsonCount(2, 'data.cores')
            ->assertJsonCount(1, 'data.connections')
            ->assertJsonPath('data.depth', 1)
            ->assertJsonPath('data.terminal_a.type', 'no_termination')
            ->assertJsonPath('data.terminal_b.type', 'no_termination');

        $cores = $response->json('data.cores');
        $this->assertSame($core1->id, $cores[0]['fiber_core_id']);
        $this->assertSame($core2->id, $cores[1]['fiber_core_id']);
    }

    // ── Multi-core chain ─────────────────────────────────────────

    public function test_three_core_chain(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp1 = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $ncp2 = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp1, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp1, $ncp2, ['sequence' => 2]);
        $seg3 = $this->segment($cable, $ncp2, $pointB, ['sequence' => 3]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);
        $core3 = $this->core($seg3, 3);

        $term1B = $this->termination($core1, 'B', $ncp1);
        $term2A = $this->termination($core2, 'A', $ncp1);
        $this->splice($term1B, $term2A);

        $term2B = $this->termination($core2, 'B', $ncp2);
        $term3A = $this->termination($core3, 'A', $ncp2);
        $this->splice($term2B, $term3A);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core2->id}/strand-path");

        $response->assertOk()
            ->assertJsonCount(3, 'data.cores')
            ->assertJsonCount(2, 'data.connections')
            ->assertJsonPath('data.depth', 2);

        $cores = $response->json('data.cores');
        $this->assertSame($core1->id, $cores[0]['fiber_core_id']);
        $this->assertSame($core2->id, $cores[1]['fiber_core_id']);
        $this->assertSame($core3->id, $cores[2]['fiber_core_id']);
    }

    // ── Physical cross-cable traversal ───────────────────────────

    public function test_physical_strand_crosses_cable_boundary(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable1 = $this->cable($company, ['name' => 'Cable 1']);
        $cable2 = $this->cable($company, ['name' => 'Cable 2']);
        $seg1 = $this->segment($cable1, $pointA, $ncp);
        $seg2 = $this->segment($cable2, $ncp, $pointB);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 1);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path?mode=physical-strand");

        $response->assertOk()
            ->assertJsonCount(2, 'data.cores')
            ->assertJsonCount(1, 'data.connections')
            ->assertJsonPath('data.cable_boundary_crossed', false)
            ->assertJsonPath('data.mode', 'physical-strand');
    }

    // ── Same-cable boundary ──────────────────────────────────────

    public function test_same_cable_strand_stops_at_cable_boundary(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable1 = $this->cable($company, ['name' => 'Cable 1']);
        $cable2 = $this->cable($company, ['name' => 'Cable 2']);
        $seg1 = $this->segment($cable1, $pointA, $ncp);
        $seg2 = $this->segment($cable2, $ncp, $pointB);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 1);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path?mode=same-cable-strand");

        $response->assertOk()
            ->assertJsonCount(1, 'data.cores')
            ->assertJsonCount(0, 'data.connections')
            ->assertJsonPath('data.cable_boundary_crossed', true)
            ->assertJsonPath('data.terminal_b.type', 'cable_boundary');
    }

    // ── Attachment-only terminal ──────────────────────────────────

    public function test_attachment_only_terminal(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);
        $termA = $this->termination($core, 'A', $pointA);

        $port = $this->passivePort($company, $pointA);
        $this->attachment($termA, $port);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path");

        $response->assertOk()
            ->assertJsonPath('data.terminal_a.type', 'passive_optical_port')
            ->assertJsonPath('data.terminal_a.id', $port->id)
            ->assertJsonPath('data.terminal_b.type', 'no_termination');
    }

    // ── Splice + attachment conflict ─────────────────────────────

    public function test_detect_conflict_returns_topology_conflict_when_both_edges_exist(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $splice = new PhysicalConnection([
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);
        $splice->id = 9001;
        $attachment = new FiberTerminationPortAttachment([
            'passive_optical_port_id' => 9999,
            'company_id' => $company->id,
        ]);
        $attachment->id = 9002;

        $result = StrandContinuityService::detectConflict($splice, $attachment, $user);

        $this->assertIsArray($result);
        $this->assertSame('topology_conflict', $result['type']);
        $this->assertNull($result['id']);
        $this->assertArrayHasKey('splice', $result['data']);
        $this->assertArrayHasKey('attachment', $result['data']);
        $this->assertSame(9001, $result['data']['splice']['id']);
        $this->assertSame('fusion_splice', $result['data']['splice']['connection_type']);
        $this->assertSame(9002, $result['data']['attachment']['id']);
        $this->assertSame(9999, $result['data']['attachment']['passive_optical_port_id']);
    }

    public function test_detect_conflict_returns_null_when_only_splice_exists(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $splice = new PhysicalConnection([
            'id' => 9001,
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        $result = StrandContinuityService::detectConflict($splice, null, $user);

        $this->assertNull($result);
    }

    public function test_detect_conflict_returns_null_when_only_attachment_exists(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $attachment = new FiberTerminationPortAttachment([
            'id' => 9002,
            'passive_optical_port_id' => 9999,
            'company_id' => $company->id,
        ]);

        $result = StrandContinuityService::detectConflict(null, $attachment, $user);

        $this->assertNull($result);
    }

    public function test_detect_conflict_returns_null_when_neither_exists(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $result = StrandContinuityService::detectConflict(null, null, $user);

        $this->assertNull($result);
    }

    public function test_detect_conflict_excludes_unscopedsplice_from_data(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company);

        // Splice belongs to other company — not in user's scope
        $splice = new PhysicalConnection([
            'id' => 9001,
            'connection_type' => 'fusion_splice',
            'company_id' => $otherCompany->id,
        ]);
        $attachment = new FiberTerminationPortAttachment([
            'id' => 9002,
            'passive_optical_port_id' => 9999,
            'company_id' => $company->id,
        ]);

        $result = StrandContinuityService::detectConflict($splice, $attachment, $user);

        $this->assertIsArray($result);
        $this->assertSame('topology_conflict', $result['type']);
        $this->assertArrayNotHasKey('splice', $result['data'], 'Unscoped splice must not appear in conflict data');
        $this->assertArrayHasKey('attachment', $result['data']);
    }

    // ── Damaged/unknown cores ────────────────────────────────────

    public function test_damaged_core_still_traversed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp, $pointB, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = FiberCore::create([
            'fiber_segment_id' => $seg2->id,
            'company_id' => $seg2->company_id,
            'core_number' => 2,
            'status' => 'damaged',
        ]);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path");

        $response->assertOk()->assertJsonCount(2, 'data.cores');

        $cores = $response->json('data.cores');
        $damagedCore = collect($cores)->firstWhere('fiber_core_id', $core2->id);
        $this->assertSame('damaged', $damagedCore['status']);
    }

    public function test_unknown_core_still_traversed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp, $pointB, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = FiberCore::create([
            'fiber_segment_id' => $seg2->id,
            'company_id' => $seg2->company_id,
            'core_number' => 2,
            'status' => 'unknown',
        ]);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path");

        $response->assertOk()->assertJsonCount(2, 'data.cores');

        $cores = $response->json('data.cores');
        $unknownCore = collect($cores)->firstWhere('fiber_core_id', $core2->id);
        $this->assertSame('unknown', $unknownCore['status']);
    }

    // ── Cycle detection ──────────────────────────────────────────

    public function test_cycle_detection(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp1 = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $ncp2 = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $ncp1, $ncp2, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp2, $ncp1, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $term1A = $this->termination($core1, 'A', $ncp1);
        $term1B = $this->termination($core1, 'B', $ncp2);
        $term2A = $this->termination($core2, 'A', $ncp2);
        $term2B = $this->termination($core2, 'B', $ncp1);

        $this->splice($term1B, $term2A);
        $this->splice($term2B, $term1A);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path");

        $response->assertOk()
            ->assertJsonPath('data.cycle_detected', true);
    }

    // ── Max depth boundary ───────────────────────────────────────

    public function test_max_depth_boundary(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp1 = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $ncp2 = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp1, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp1, $ncp2, ['sequence' => 2]);
        $seg3 = $this->segment($cable, $ncp2, $pointB, ['sequence' => 3]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);
        $core3 = $this->core($seg3, 3);

        $term1B = $this->termination($core1, 'B', $ncp1);
        $term2A = $this->termination($core2, 'A', $ncp1);
        $this->splice($term1B, $term2A);

        $term2B = $this->termination($core2, 'B', $ncp2);
        $term3A = $this->termination($core3, 'A', $ncp2);
        $this->splice($term2B, $term3A);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path?max_depth=1");

        $response->assertOk()
            ->assertJsonPath('data.truncated', true)
            ->assertJsonPath('data.depth', 1);
    }

    public function test_max_depth_exact_boundary_not_truncated(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp, $pointB, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path?max_depth=1");

        $response->assertOk()
            ->assertJsonPath('data.truncated', false)
            ->assertJsonPath('data.depth', 1);
    }

    // ── Deterministic traversal ordering ──────────────────────────

    public function test_traversal_order_is_not_sorted_by_core_number(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp, $pointB, ['sequence' => 2]);
        $core1 = $this->core($seg1, 5);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core1->id}/strand-path");

        $response->assertOk();

        $cores = $response->json('data.cores');
        $this->assertSame($core1->id, $cores[0]['fiber_core_id']);
        $this->assertSame($core2->id, $cores[1]['fiber_core_id']);
        $this->assertSame(5, $cores[0]['core_number']);
        $this->assertSame(2, $cores[1]['core_number']);
    }

    // ── Starting core authorization ───────────────────────────────

    public function test_unauthenticated_user_rejected(): void
    {
        $company = Company::factory()->create();
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);

        $response = $this->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path");

        $response->assertUnauthorized();
    }

    public function test_user_without_permission_rejected(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path");

        $response->assertForbidden();
    }

    // ── Downstream scope boundary ────────────────────────────────

    public function test_user_cannot_access_other_company_core(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->user($companyA);

        $siteB = Site::factory()->create(['company_id' => $companyB->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $siteB->id, 'company_id' => $companyB->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $siteB->id, 'company_id' => $companyB->id]);

        $cableB = $this->cable($companyB);
        $segB = $this->segment($cableB, $pointA, $pointB);
        $coreB = $this->core($segB, 1);

        $response = $this->actingAs($userA)
            ->getJson("/api/v1/fim/fiber-cores/{$coreB->id}/strand-path");

        $response->assertForbidden();
    }

    // ── No hidden-resource leakage ───────────────────────────────

    public function test_scope_boundary_leaks_no_ids(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->user($companyA);

        $siteB = Site::factory()->create(['company_id' => $companyB->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $siteB->id, 'company_id' => $companyB->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $siteB->id, 'company_id' => $companyB->id]);

        $cableB = $this->cable($companyB);
        $segB = $this->segment($cableB, $pointA, $pointB);
        $coreB = $this->core($segB, 1);

        $response = $this->actingAs($userA)
            ->getJson("/api/v1/fim/fiber-cores/{$coreB->id}/strand-path");

        $response->assertForbidden();

        $message = $response->json('message');
        $this->assertStringNotContainsString((string) $coreB->id, $message,
            'Hidden core ID must not appear in error message');
        $this->assertStringNotContainsString((string) $segB->id, $message,
            'Hidden segment ID must not appear in error message');
    }

    // ── Invalid mode / max_depth ─────────────────────────────────

    public function test_invalid_mode_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path?mode=invalid");

        $response->assertUnprocessable();
    }

    public function test_invalid_max_depth_zero_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path?max_depth=0");

        $response->assertUnprocessable();
    }

    public function test_invalid_max_depth_over_50_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path?max_depth=51");

        $response->assertUnprocessable();
    }

    // ── Existing FIM semantics intact ────────────────────────────

    public function test_existing_topology_traversal_unchanged(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $ncp);
        $core = $this->core($segment, 1);
        $termA = $this->termination($core, 'A', $pointA);
        $termB = $this->termination($core, 'B', $ncp);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/topology/terminations/{$termA->id}");

        $response->assertOk()->assertJsonStructure([
            'data' => ['nodes', 'edges', 'start_node', 'depth', 'truncated'],
        ]);
    }

    // ── Scope boundary stops traversal, no continuation ───────────

    public function test_scope_boundary_stops_traversal_no_leak(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->user($companyA);

        $siteB = Site::factory()->create(['company_id' => $companyB->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $siteB->id, 'company_id' => $companyB->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $siteB->id, 'company_id' => $companyB->id]);

        $cableB = $this->cable($companyB);
        $segB = $this->segment($cableB, $pointA, $pointB);
        $coreB = $this->core($segB, 1);

        $response = $this->actingAs($userA)
            ->getJson("/api/v1/fim/fiber-cores/{$coreB->id}/strand-path");

        $response->assertForbidden();
    }

    // ── Starting core in A-side terminal position ────────────────

    public function test_single_core_alone(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core->id}/strand-path");

        $response->assertOk()
            ->assertJsonFragment([
                'fiber_core_id' => $core->id,
            ])
            ->assertJsonCount(1, 'data.cores')
            ->assertJsonCount(0, 'data.connections')
            ->assertJsonPath('data.terminal_a.type', 'no_termination')
            ->assertJsonPath('data.terminal_b.type', 'no_termination');
    }

    // ── Path ordering: starting core is always in middle ──────────

    public function test_starting_core_always_in_middle_of_cores(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncp = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $pointB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        $cable = $this->cable($company);
        $seg1 = $this->segment($cable, $pointA, $ncp, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $ncp, $pointB, ['sequence' => 2]);
        $core1 = $this->core($seg1, 1);
        $core2 = $this->core($seg2, 2);

        $termA = $this->termination($core1, 'B', $ncp);
        $termB = $this->termination($core2, 'A', $ncp);
        $this->splice($termA, $termB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-cores/{$core2->id}/strand-path");

        $response->assertOk();

        $cores = $response->json('data.cores');
        $this->assertCount(2, $cores);
        $this->assertSame($core1->id, $cores[0]['fiber_core_id']);
        $this->assertSame($core2->id, $cores[1]['fiber_core_id']);
    }
}
