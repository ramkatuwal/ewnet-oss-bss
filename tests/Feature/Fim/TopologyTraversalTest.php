<?php

namespace Tests\Feature\Fim;

use App\Models\Asset;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\PhysicalConnection;
use App\Models\Site;
use App\Models\SplitterBranch;
use App\Models\SplitterProfile;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopologyTraversalTest extends TestCase
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
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        return $user->refresh();
    }

    protected function permissions(): array
    {
        return [
            'fim.fiber-terminations.view',
            'fim.fiber-cores.view',
            'fim.fiber-segments.view',
            'fim.cables.view',
            'fim.connection-points.view',
            'fim.passive-optical-ports.view',
            'assets.view',
        ];
    }

    /** @return array{0: NetworkConnectionPoint, 1: Site, 2: Asset} */
    protected function endpoint(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'ODF',
        ]);
        $ncp = NetworkConnectionPoint::factory()->create([
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
        ]);

        return [$ncp, $site, $asset];
    }

    protected function terminationAt(Company $company, NetworkConnectionPoint $ncp): FiberTermination
    {
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $otherNcp = NetworkConnectionPoint::factory()->create(['company_id' => $company->id]);
        $segment = FiberSegment::create([
            'fiber_cable_id' => $cable->id,
            'endpoint_a_id' => $ncp->id,
            'endpoint_b_id' => $otherNcp->id,
            'company_id' => $company->id,
            'sequence' => 1,
            'length_meters' => 200,
            'status' => 'ACTIVE',
        ]);
        $core = FiberCore::create([
            'fiber_segment_id' => $segment->id,
            'company_id' => $company->id,
            'core_number' => 1,
            'status' => 'ACTIVE',
        ]);

        return FiberTermination::create([
            'fiber_core_id' => $core->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $ncp->id,
            'segment_end' => 'A',
        ]);
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

    public function test_traverse_from_termination_returns_single_node_when_no_edges(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}");

        $response->assertOk()
            ->assertJsonPath('data.start_node', "FiberTermination:{$t1->id}")
            ->assertJsonPath('data.depth', 0)
            ->assertJsonPath('data.truncated', false)
            ->assertJsonCount(1, 'data.nodes')
            ->assertJsonCount(0, 'data.edges');
    }

    public function test_traverse_from_port_returns_single_node_when_no_edges(): void
    {
        $company = Company::factory()->create();
        [$ncp, $site, $asset] = $this->endpoint($company);
        $port = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'network_connection_point_id' => $ncp->id,
            'company_id' => $company->id,
        ]);
        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/ports/{$port->id}");

        $response->assertOk()
            ->assertJsonPath('data.start_node', "PassiveOpticalPort:{$port->id}")
            ->assertJsonPath('data.depth', 0)
            ->assertJsonPath('data.truncated', false)
            ->assertJsonCount(1, 'data.nodes')
            ->assertJsonCount(0, 'data.edges');
    }

    public function test_traverse_through_splice(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $t2 = $this->terminationAt($company, $ncp);

        PhysicalConnection::create([
            'termination_a_id' => min($t1->id, $t2->id),
            'termination_b_id' => max($t1->id, $t2->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}");

        $response->assertOk()
            ->assertJsonPath('data.depth', 1)
            ->assertJsonCount(2, 'data.nodes')
            ->assertJsonCount(1, 'data.edges')
            ->assertJsonPath('data.edges.0.type', 'PhysicalConnection');
    }

    public function test_traverse_through_attachment(): void
    {
        $company = Company::factory()->create();
        [$ncp, $site, $asset] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $port = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'network_connection_point_id' => $ncp->id,
            'company_id' => $company->id,
        ]);

        $this->insertAttachment($t1, $port, $company);

        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}");

        $response->assertOk()
            ->assertJsonPath('data.depth', 1)
            ->assertJsonCount(2, 'data.nodes')
            ->assertJsonCount(1, 'data.edges')
            ->assertJsonPath('data.edges.0.type', 'FiberTerminationPortAttachment');
    }

    public function test_traverse_through_splitter_fanout(): void
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'SPLITTER',
        ]);
        $ncp = NetworkConnectionPoint::factory()->create([
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
        ]);
        $input = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $ncp->id,
            'port_role' => 'splitter_input',
        ]);
        $output1 = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $ncp->id,
            'port_role' => 'splitter_output',
        ]);
        $output2 = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $ncp->id,
            'port_role' => 'splitter_output',
        ]);

        $profile = SplitterProfile::create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_count' => 1,
            'output_port_count' => 2,
        ]);

        SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_id' => $input->id,
            'output_port_id' => $output1->id,
        ]);
        SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_id' => $input->id,
            'output_port_id' => $output2->id,
        ]);

        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/ports/{$input->id}");

        $response->assertOk()
            ->assertJsonPath('data.depth', 1)
            ->assertJsonCount(3, 'data.nodes')
            ->assertJsonCount(2, 'data.edges')
            ->assertJsonPath('data.edges.0.type', 'SplitterBranch');
    }

    public function test_depth_limit_truncates_traversal(): void
    {
        $company = Company::factory()->create();
        [$ncp, $site, $asset] = $this->endpoint($company);

        $t1 = $this->terminationAt($company, $ncp);
        $t2 = $this->terminationAt($company, $ncp);
        $t3 = $this->terminationAt($company, $ncp);
        $port1 = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'network_connection_point_id' => $ncp->id,
            'company_id' => $company->id,
        ]);

        PhysicalConnection::create([
            'termination_a_id' => min($t1->id, $t2->id),
            'termination_b_id' => max($t1->id, $t2->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);
        $this->insertAttachment($t3, $port1, $company);

        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}?max_depth=1");

        $response->assertOk()
            ->assertJsonPath('data.truncated', true)
            ->assertJsonPath('data.depth', 1)
            ->assertJsonCount(2, 'data.nodes')
            ->assertJsonCount(1, 'data.edges');
    }

    public function test_direction_both_follows_all_edges(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $t2 = $this->terminationAt($company, $ncp);

        PhysicalConnection::create([
            'termination_a_id' => min($t1->id, $t2->id),
            'termination_b_id' => max($t1->id, $t2->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}?direction=both");

        $response->assertOk()
            ->assertJsonPath('data.depth', 1)
            ->assertJsonCount(2, 'data.nodes');
    }

    public function test_direction_traceback_follows_reverse_splitter_edges(): void
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'SPLITTER',
        ]);
        $ncp = NetworkConnectionPoint::factory()->create([
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
        ]);
        $input = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $ncp->id,
            'port_role' => 'splitter_input',
        ]);
        $output = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $ncp->id,
            'port_role' => 'splitter_output',
        ]);

        $profile = SplitterProfile::create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_count' => 1,
            'output_port_count' => 1,
        ]);

        SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_id' => $input->id,
            'output_port_id' => $output->id,
        ]);

        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/ports/{$output->id}?direction=trace-back");

        $response->assertOk()
            ->assertJsonPath('data.depth', 1)
            ->assertJsonCount(2, 'data.nodes')
            ->assertJsonCount(1, 'data.edges')
            ->assertJsonPath('data.edges.0.type', 'SplitterBranch');
    }

    public function test_unauthenticated_returns_401(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);

        $this->getJson("/api/v1/fim/topology/terminations/{$t1->id}")
            ->assertUnauthorized();
    }

    public function test_invisible_start_node_returns_403(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        [$ncp] = $this->endpoint($companyA);
        $t1 = $this->terminationAt($companyA, $ncp);
        $user = $this->user($companyB, $this->permissions());

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}")
            ->assertForbidden();
    }

    public function test_nonexistent_termination_returns_404(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/fim/topology/terminations/999999')
            ->assertNotFound();
    }

    public function test_invalid_direction_returns_422(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $user = $this->user($company, $this->permissions());

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}?direction=invalid")
            ->assertUnprocessable();
    }

    public function test_containment_data_included_by_default(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}");

        $response->assertOk();
        $nodeData = $response->json('data.nodes.0.data');
        $this->assertArrayHasKey('fiber_core', $nodeData);
        $this->assertArrayHasKey('fiber_segment', $nodeData);
        $this->assertArrayHasKey('fiber_cable', $nodeData);
        $this->assertArrayHasKey('network_connection_point', $nodeData);
        $this->assertArrayHasKey('point_type', $nodeData['network_connection_point']);
        $this->assertArrayHasKey('name', $nodeData['network_connection_point']);
    }

    public function test_containment_data_excluded_when_disabled(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}?include_containment=false");

        $response->assertOk();
        $nodeData = $response->json('data.nodes.0.data');
        $this->assertArrayNotHasKey('fiber_core', $nodeData);
        $this->assertArrayNotHasKey('fiber_segment', $nodeData);
        $this->assertArrayNotHasKey('fiber_cable', $nodeData);
    }

    public function test_soft_deleted_edges_excluded_from_traversal(): void
    {
        $company = Company::factory()->create();
        [$ncp] = $this->endpoint($company);
        $t1 = $this->terminationAt($company, $ncp);
        $t2 = $this->terminationAt($company, $ncp);

        $conn = PhysicalConnection::create([
            'termination_a_id' => min($t1->id, $t2->id),
            'termination_b_id' => max($t1->id, $t2->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);
        $conn->delete();

        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}");

        $response->assertOk()
            ->assertJsonPath('data.depth', 0)
            ->assertJsonCount(1, 'data.nodes')
            ->assertJsonCount(0, 'data.edges');
    }

    public function test_multi_hop_path_through_mixed_edge_types(): void
    {
        $company = Company::factory()->create();
        [$ncp, $site, $asset] = $this->endpoint($company);

        // Build a valid mixed-edge graph (no termination has both splice and attachment):
        // Splice path:  T1 →splice→ T2
        // Attachment path: T3 →attachment→ portOut →splitter→ portIn →attachment→ T4
        // Both subgraphs exist in the same NCP scope.
        $t1 = $this->terminationAt($company, $ncp);
        $t2 = $this->terminationAt($company, $ncp);

        PhysicalConnection::create([
            'termination_a_id' => min($t1->id, $t2->id),
            'termination_b_id' => max($t1->id, $t2->id),
            'connection_type' => 'fusion_splice',
            'company_id' => $company->id,
        ]);

        // Splitter asset on a different NCP
        $splitterSite = Site::factory()->create(['company_id' => $company->id]);
        $splitterAsset = Asset::factory()->create([
            'site_id' => $splitterSite->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'SPLITTER',
        ]);
        $splitterNcp = NetworkConnectionPoint::factory()->create([
            'site_id' => $splitterSite->id,
            'asset_id' => $splitterAsset->id,
            'company_id' => $company->id,
        ]);

        $portOut = PassiveOpticalPort::factory()->create([
            'asset_id' => $splitterAsset->id,
            'network_connection_point_id' => $ncp->id,
            'company_id' => $company->id,
            'port_role' => 'splitter_output',
        ]);

        $portIn = PassiveOpticalPort::factory()->create([
            'asset_id' => $splitterAsset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $splitterNcp->id,
            'port_role' => 'splitter_input',
        ]);

        $t3 = $this->terminationAt($company, $ncp);
        $t4 = $this->terminationAt($company, $splitterNcp);

        $profile = SplitterProfile::create([
            'asset_id' => $splitterAsset->id,
            'company_id' => $company->id,
            'input_port_count' => 1,
            'output_port_count' => 1,
        ]);
        SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $splitterAsset->id,
            'company_id' => $company->id,
            'input_port_id' => $portIn->id,
            'output_port_id' => $portOut->id,
        ]);

        $this->insertAttachment($t3, $portOut, $company);
        $this->insertAttachment($t4, $portIn, $company);

        $user = $this->user($company, $this->permissions());

        // Verify splice path from T1
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t1->id}");

        $response->assertOk()
            ->assertJsonPath('data.depth', 1)
            ->assertJsonCount(2, 'data.nodes')
            ->assertJsonCount(1, 'data.edges');

        $edgeTypes = array_column($response->json('data.edges'), 'type');
        $this->assertContains('PhysicalConnection', $edgeTypes);

        // Verify attachment+splitter path from T3
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/fim/topology/terminations/{$t3->id}");

        $response->assertOk()
            ->assertJsonPath('data.depth', 3)
            ->assertJsonCount(4, 'data.nodes')
            ->assertJsonCount(3, 'data.edges');

        $edgeTypes = array_column($response->json('data.edges'), 'type');
        $this->assertContains('FiberTerminationPortAttachment', $edgeTypes);
        $this->assertContains('SplitterBranch', $edgeTypes);

        $nodeTypes = array_column($response->json('data.nodes'), 'type');
        $this->assertContains('FiberTermination', $nodeTypes);
        $this->assertContains('PassiveOpticalPort', $nodeTypes);
    }
}
