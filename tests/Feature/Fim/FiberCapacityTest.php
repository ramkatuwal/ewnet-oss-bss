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
use App\Models\SplitterBranch;
use App\Models\SplitterProfile;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FiberCapacityTest extends TestCase
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
            'fim.splitter-profiles.view', 'fim.passive-optical-ports.view',
            'fim.splitter-branches.view',
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
            'cable_code' => 'FC-TEST-'.Str::random(6),
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

    // ── Segment Capacity Tests ──────────────────────────────────

    public function test_segment_capacity_empty(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 4]);
        $segment = $this->segment($cable, $pointA, $pointB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'nominal_capacity' => 4,
            'inventoried_count' => 0,
            'unaccounted' => 4,
            'inventory_complete' => false,
            'termination_count' => 0,
            'connected_termination_count' => 0,
        ]);
    }

    public function test_segment_capacity_full_inventory_no_terminations(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 2]);
        $segment = $this->segment($cable, $pointA, $pointB);
        $this->core($segment, 1);
        $this->core($segment, 2);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'nominal_capacity' => 2,
            'inventoried_count' => 2,
            'unaccounted' => 0,
            'inventory_complete' => true,
            'termination_count' => 0,
            'unterminated_core_count' => 2,
            'fully_terminated_core_count' => 0,
            'partially_terminated_core_count' => 0,
            'connected_termination_count' => 0,
            'no_external_connectivity_core_count' => 2,
        ]);
    }

    public function test_segment_capacity_partial_termination(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 1]);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);
        $this->termination($core, 'A', $pointA);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'termination_count' => 1,
            'fully_terminated_core_count' => 0,
            'partially_terminated_core_count' => 1,
            'unterminated_core_count' => 0,
            'connected_termination_count' => 0,
            'no_external_connectivity_core_count' => 1,
        ]);
    }

    public function test_segment_capacity_full_termination(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 1]);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);
        $this->termination($core, 'A', $pointA);
        $this->termination($core, 'B', $pointB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'termination_count' => 2,
            'fully_terminated_core_count' => 1,
            'partially_terminated_core_count' => 0,
            'unterminated_core_count' => 0,
        ]);
    }

    public function test_segment_capacity_connected_termination(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 2]);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core1 = $this->core($segment, 1);
        $core2 = $this->core($segment, 2);

        $term1 = $this->termination($core1, 'A', $pointA);
        $term2 = $this->termination($core2, 'A', $pointA);

        PhysicalConnection::create([
            'company_id' => $company->id,
            'termination_a_id' => $term1->id,
            'termination_b_id' => $term2->id,
            'connection_type' => 'fusion_splice',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'connected_termination_count' => 2,
            'no_external_connectivity_core_count' => 0,
            'partially_connected_core_count' => 0,
            'fully_connected_core_count' => 2,
        ]);
    }

    public function test_segment_capacity_partial_connectivity(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 1]);
        $segment = $this->segment($cable, $pointA, $pointB);
        $core = $this->core($segment, 1);
        $termA = $this->termination($core, 'A', $pointA);
        $this->termination($core, 'B', $pointB);

        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'ODF']);
        $port = PassiveOpticalPort::create([
            'asset_id' => $asset->id,
            'network_connection_point_id' => $pointA->id,
            'company_id' => $company->id,
            'port_number' => '1',
            'port_role' => 'generic',
        ]);

        FiberTerminationPortAttachment::create([
            'fiber_termination_id' => $termA->id,
            'passive_optical_port_id' => $port->id,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'connected_termination_count' => 1,
            'partially_connected_core_count' => 1,
            'fully_connected_core_count' => 0,
        ]);
    }

    public function test_segment_capacity_data_inconsistency(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 2]);
        $segment = $this->segment($cable, $pointA, $pointB);
        $this->core($segment, 1);
        $this->core($segment, 2);
        $this->core($segment, 3);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'nominal_capacity' => 2,
            'inventoried_count' => 3,
            'unaccounted' => 0,
            'data_inconsistency' => true,
        ]);
    }

    public function test_segment_capacity_unauthorized_returns_403(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company);
        $segment = $this->segment($cable, $pointA, $pointB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertForbidden();
    }

    // ── Cable Capacity Tests ─────────────────────────────────────

    public function test_cable_capacity_segments(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $pointC = NetworkConnectionPoint::factory()->create([
            'site_id' => $pointA->site_id,
            'company_id' => $company->id,
        ]);
        $cable = $this->cable($company, ['fiber_count' => 2]);
        $seg1 = $this->segment($cable, $pointA, $pointB, ['sequence' => 1]);
        $seg2 = $this->segment($cable, $pointB, $pointC, ['sequence' => 2]);
        $this->core($seg1, 1);
        $this->core($seg1, 2);
        $this->core($seg2, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/cables/{$cable->id}/capacity");

        $response->assertOk()
            ->assertJsonFragment([
                'fiber_count' => 2,
                'segment_count' => 2,
                'fully_inventoried_segment_count' => 1,
                'partially_inventoried_segment_count' => 1,
                'inventory_complete_across_all_segments' => false,
            ])
            ->assertJsonCount(2, 'data.segments');
    }

    public function test_cable_capacity_all_complete(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 1]);
        $seg = $this->segment($cable, $pointA, $pointB);
        $this->core($seg, 1);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/cables/{$cable->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'fully_inventoried_segment_count' => 1,
            'partially_inventoried_segment_count' => 0,
            'inventory_complete_across_all_segments' => true,
        ]);
    }

    public function test_cable_capacity_unauthorized_returns_403(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $cable = $this->cable($company);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/cables/{$cable->id}/capacity");

        $response->assertForbidden();
    }

    // ── Splitter Capacity Tests ──────────────────────────────────

    protected function splitterSetup(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'SPLITTER',
        ]);
        $point = NetworkConnectionPoint::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
        ]);
        $profile = SplitterProfile::create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_count' => 1,
            'output_port_count' => 2,
        ]);

        return [$profile, $asset, $point];
    }

    protected function splitterPorts(SplitterProfile $profile, NetworkConnectionPoint $point): array
    {
        $input = PassiveOpticalPort::create([
            'asset_id' => $profile->asset_id,
            'network_connection_point_id' => $point->id,
            'company_id' => $profile->company_id,
            'port_number' => 'IN-1',
            'port_role' => 'splitter_input',
        ]);
        $out1 = PassiveOpticalPort::create([
            'asset_id' => $profile->asset_id,
            'network_connection_point_id' => $point->id,
            'company_id' => $profile->company_id,
            'port_number' => 'OUT-1',
            'port_role' => 'splitter_output',
        ]);
        $out2 = PassiveOpticalPort::create([
            'asset_id' => $profile->asset_id,
            'network_connection_point_id' => $point->id,
            'company_id' => $profile->company_id,
            'port_number' => 'OUT-2',
            'port_role' => 'splitter_output',
        ]);

        return [$input, $out1, $out2];
    }

    public function test_splitter_capacity_no_branches(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$profile, $asset, $point] = $this->splitterSetup($company);
        [$input, $out1, $out2] = $this->splitterPorts($profile, $point);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/splitter-profiles/{$profile->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'total_outputs' => 2,
            'generated_output_ports' => 2,
            'live_branches' => 0,
            'attached_outputs' => 0,
            'unused_generated_outputs' => 2,
            'historical_branch_count' => 0,
            'historically_used_output_count' => 0,
        ]);
    }

    public function test_splitter_capacity_with_branches(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$profile, $asset, $point] = $this->splitterSetup($company);
        [$input, $out1, $out2] = $this->splitterPorts($profile, $point);

        SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_id' => $input->id,
            'output_port_id' => $out1->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/splitter-profiles/{$profile->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'live_branches' => 1,
            'attached_outputs' => 1,
            'unused_generated_outputs' => 1,
            'historical_branch_count' => 1,
            'historically_used_output_count' => 1,
        ]);
    }

    public function test_splitter_capacity_historical_distinct_outputs(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$profile, $asset, $point] = $this->splitterSetup($company);
        [$input, $out1, $out2] = $this->splitterPorts($profile, $point);

        $branch = SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_id' => $input->id,
            'output_port_id' => $out1->id,
        ]);

        $branch->delete();

        SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_id' => $input->id,
            'output_port_id' => $out1->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/splitter-profiles/{$profile->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'live_branches' => 1,
            'historical_branch_count' => 2,
            'historically_used_output_count' => 1,
        ]);
    }

    public function test_splitter_capacity_unauthorized_returns_403(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        [$profile] = $this->splitterSetup($company);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/splitter-profiles/{$profile->id}/capacity");

        $response->assertForbidden();
    }

    // ── Mixed Connectivity Tests ─────────────────────────────────

    public function test_segment_mixed_core_states(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        [$pointA, $pointB] = $this->endpoint($company);
        $cable = $this->cable($company, ['fiber_count' => 3]);
        $segment = $this->segment($cable, $pointA, $pointB);

        $core1 = $this->core($segment, 1);
        $core2 = $this->core($segment, 2);
        $core3 = $this->core($segment, 3);

        $t1 = $this->termination($core1, 'A', $pointA);
        $t2 = $this->termination($core2, 'A', $pointA);

        PhysicalConnection::create([
            'company_id' => $company->id,
            'termination_a_id' => $t1->id,
            'termination_b_id' => $t2->id,
            'connection_type' => 'fusion_splice',
        ]);

        $this->termination($core3, 'A', $pointA);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/fim/fiber-segments/{$segment->id}/capacity");

        $response->assertOk()->assertJsonFragment([
            'termination_count' => 3,
            'fully_terminated_core_count' => 0,
            'partially_terminated_core_count' => 3,
            'unterminated_core_count' => 0,
            'connected_termination_count' => 2,
            'fully_connected_core_count' => 2,
            'partially_connected_core_count' => 0,
            'no_external_connectivity_core_count' => 1,
        ]);
    }
}
