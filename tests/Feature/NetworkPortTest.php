<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Models\NetworkPort;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NetworkPortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function user(Company $company, array $permissions = []): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    protected function networkAsset(Company $company, string $type = 'OLT'): Asset
    {
        return Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => $type,
        ]);
    }

    protected function infraAsset(Company $company): Asset
    {
        return Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'ODF',
        ]);
    }

    // --- CRUD Tests ---

    public function test_create_network_port_on_network_asset(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'PON1',
            'name' => 'PON Port 1',
            'connector_type' => 'SC/APC',
            'port_direction' => 'downstream',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['port_key' => 'PON1']);
        $this->assertDatabaseHas('network_ports', ['asset_id' => $asset->id, 'port_key' => 'PON1', 'company_id' => $company->id]);
    }

    public function test_list_network_ports(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'assets.view']);
        $asset = $this->networkAsset($company);

        NetworkPort::factory()->count(3)->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/network-ports");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_show_network_port(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/fim/network-ports/{$port->id}");

        $response->assertOk();
        $response->assertJsonFragment(['port_key' => $port->port_key]);
    }

    public function test_update_network_port(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'name' => 'Updated Name',
            'connector_type' => 'LC/APC',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'name' => 'Updated Name', 'connector_type' => 'LC/APC']);
    }

    public function test_delete_network_port(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.delete', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$port->id}");

        $response->assertOk();
        $this->assertSoftDeleted('network_ports', ['id' => $port->id]);
    }

    // --- Validation Tests ---

    public function test_reject_non_network_asset(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->infraAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'P1',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['asset_id']);
    }

    public function test_reject_duplicate_port_key_within_asset(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);
        NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'port_key' => 'PON1']);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'PON1',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['port_key']);
    }

    public function test_reject_missing_port_key(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['port_key']);
    }

    public function test_reject_invalid_port_direction(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'P1',
            'port_direction' => 'invalid',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['port_direction']);
    }

    // --- Immutability Tests ---

    public function test_port_key_is_immutable_on_update(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'port_key' => 'ORIGINAL']);

        $response = $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'port_key' => 'CHANGED',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'port_key' => 'ORIGINAL']);
    }

    public function test_asset_id_is_immutable_on_update(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $asset = $this->networkAsset($company);
        $otherAsset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'asset_id' => $otherAsset->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'asset_id' => $asset->id]);
    }

    public function test_company_id_is_immutable_on_update(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'company_id' => $otherCompany->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'company_id' => $company->id]);
    }

    // --- Port Key History ---

    public function test_soft_deleted_port_key_not_reusable(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'net.network-ports.delete', 'assets.view']);
        $asset = $this->networkAsset($company);

        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'port_key' => 'PON1']);
        $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$port->id}");

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'PON1',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['port_key']);
    }

    // --- Authorization Tests ---

    public function test_unauthenticated_cannot_access(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $this->getJson("/api/v1/fim/network-ports/{$port->id}")->assertUnauthorized();
        $this->postJson("/api/v1/assets/{$asset->id}/network-ports", ['port_key' => 'P1'])->assertUnauthorized();
        $this->patchJson("/api/v1/fim/network-ports/{$port->id}", ['name' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/v1/fim/network-ports/{$port->id}")->assertUnauthorized();
    }

    public function test_missing_permission_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $this->actingAs($user)->getJson("/api/v1/fim/network-ports/{$port->id}")->assertForbidden();
        $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", ['port_key' => 'P1'])->assertForbidden();
    }

    public function test_scope_boundary_other_company_rejected(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->user($companyA, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $assetB = $this->networkAsset($companyB);

        $this->actingAs($userA)->postJson("/api/v1/assets/{$assetB->id}/network-ports", [
            'port_key' => 'P1',
        ])->assertForbidden();
    }

    public function test_scope_boundary_cannot_view_other_company_port(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->user($companyA, ['net.network-ports.view', 'assets.view']);
        $assetB = $this->networkAsset($companyB);
        $port = NetworkPort::factory()->create(['asset_id' => $assetB->id, 'company_id' => $companyB->id]);

        $this->actingAs($userA)->getJson("/api/v1/fim/network-ports/{$port->id}")->assertForbidden();
    }

    // --- NCP XOR Tests ---

    public function test_ncp_may_reference_network_port(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.connection-points.create', 'fim.connection-points.view', 'net.network-ports.view', 'assets.view']);
        $asset = $this->networkAsset($company);
        $site = Site::factory()->create(['company_id' => $company->id]);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/fim/connection-points', [
            'point_type' => 'olt_port',
            'network_port_id' => $port->id,
            'site_id' => $site->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('network_connection_points', ['network_port_id' => $port->id, 'asset_interface_id' => null]);
    }

    public function test_ncp_may_reference_asset_interface(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.connection-points.create', 'fim.connection-points.view', 'assets.view']);
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = $this->networkAsset($company);
        $interface = $asset->interfaces()->create(['name' => 'eth0', 'type' => 'ethernet']);

        $response = $this->actingAs($user)->postJson('/api/v1/fim/connection-points', [
            'point_type' => 'switch_port',
            'asset_interface_id' => $interface->id,
            'site_id' => $site->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('network_connection_points', ['asset_interface_id' => $interface->id, 'network_port_id' => null]);
    }

    public function test_ncp_may_reference_neither(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.connection-points.create', 'fim.connection-points.view', 'assets.view']);
        $site = Site::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/fim/connection-points', [
            'point_type' => 'junction',
            'site_id' => $site->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('network_connection_points', ['asset_interface_id' => null, 'network_port_id' => null]);
    }

    public function test_ncp_cannot_reference_both_application(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['fim.connection-points.create', 'fim.connection-points.view', 'net.network-ports.view', 'assets.view']);
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);
        $interface = $asset->interfaces()->create(['name' => 'eth0', 'type' => 'ethernet']);

        $response = $this->actingAs($user)->postJson('/api/v1/fim/connection-points', [
            'point_type' => 'olt_port',
            'network_port_id' => $port->id,
            'asset_interface_id' => $interface->id,
            'site_id' => $site->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['network_port_id']);
    }

    public function test_ncp_cannot_reference_both_raw_db_bypass(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        $site = Site::factory()->create(['company_id' => $company->id]);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);
        $interface = $asset->interfaces()->create(['name' => 'eth0', 'type' => 'ethernet']);

        try {
            DB::table('network_connection_points')->insert([
                'point_type' => 'olt_port',
                'network_port_id' => $port->id,
                'asset_interface_id' => $interface->id,
                'site_id' => $site->id,
                'company_id' => $company->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected QueryException for XOR constraint violation');
        } catch (\Exception $e) {
            $this->assertStringContainsString('ncp_asset_interface_or_network_port_check', $e->getMessage());
        }
    }

    public function test_cross_company_ncp_network_port_rejected(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = $this->user($companyA, ['fim.connection-points.create', 'net.network-ports.view', 'assets.view']);
        $siteA = Site::factory()->create(['company_id' => $companyA->id]);
        $assetB = $this->networkAsset($companyB);
        $portB = NetworkPort::factory()->create(['asset_id' => $assetB->id, 'company_id' => $companyB->id]);

        $response = $this->actingAs($userA)->postJson('/api/v1/fim/connection-points', [
            'point_type' => 'olt_port',
            'network_port_id' => $portB->id,
            'site_id' => $siteA->id,
            'company_id' => $companyA->id,
            'status' => 'active',
        ]);

        $response->assertUnprocessable();
    }

    // --- History Protection Tests ---

    public function test_cannot_delete_port_referenced_by_ncp(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.delete', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);
        $site = Site::factory()->create(['company_id' => $company->id]);
        NetworkConnectionPoint::factory()->create([
            'network_port_id' => $port->id,
            'site_id' => $site->id,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$port->id}");

        $response->assertUnprocessable();
    }

    public function test_soft_deleted_network_port_not_anchorable(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'net.network-ports.delete', 'fim.connection-points.create', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        // Soft-delete the port
        $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$port->id}");

        $site = Site::factory()->create(['company_id' => $company->id]);
        $response = $this->actingAs($user)->postJson('/api/v1/fim/connection-points', [
            'point_type' => 'olt_port',
            'network_port_id' => $port->id,
            'site_id' => $site->id,
            'company_id' => $company->id,
        ]);

        $response->assertUnprocessable();
    }

    // --- Topology Boundary Tests ---

    public function test_network_port_creates_no_topology_edge(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        // NetworkPort has no physical_connections, fiber_termination_port_attachments, or splitter_branches relationships
        $this->assertNull($port->physicalConnections ?? null);
        $this->assertNull($port->fiberTerminationPortAttachments ?? null);
        $this->assertNull($port->splitterBranches ?? null);

        // Verify the model only has expected relationships
        $relations = (new \ReflectionClass($port))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $relationNames = array_map(fn ($m) => $m->getName(), $relations);
        $this->assertContains('asset', $relationNames);
        $this->assertContains('company', $relationNames);
        $this->assertContains('networkConnectionPoints', $relationNames);
    }

    public function test_collection_count_not_leaked(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'assets.view']);
        $asset = $this->networkAsset($company);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/network-ports");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_fim_005_behavior_unchanged(): void
    {
        // Verify FIM topology traversal still works after NCP extension
        $company = Company::factory()->create();
        $user = $this->user($company, [
            'fim.fiber-terminations.view',
            'fim.passive-optical-ports.view',
            'fim.connection-points.view',
            'fim.physical-connections.view',
            'fim.fiber-segments.view',
            'fim.cables.view',
            'fim.fiber-cores.view',
            'assets.view',
        ]);

        // Create minimal FIM chain: Cable -> Segment -> Core -> Termination -> NCP
        $site = Site::factory()->create(['company_id' => $company->id]);
        $ncpA = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $ncpB = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id, 'start_site_id' => $site->id, 'end_site_id' => $site->id]);
        $segment = FiberSegment::factory()->create(['fiber_cable_id' => $cable->id, 'company_id' => $company->id, 'endpoint_a_id' => $ncpA->id, 'endpoint_b_id' => $ncpB->id]);
        $core = FiberCore::factory()->create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id]);

        // FiberTermination has no factory — create manually.
        $termination = FiberTermination::create([
            'fiber_core_id' => $core->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $ncpA->id,
            'segment_end' => 'A',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/fim/topology/terminations/{$termination->id}");

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['nodes', 'edges']]);
    }

    public function test_unauthenticated_cannot_list(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);

        $this->getJson("/api/v1/assets/{$asset->id}/network-ports")->assertUnauthorized();
    }

    public function test_cannot_update_deleted_port(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.update', 'net.network-ports.delete', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$port->id}");

        // Route model binding does not use withTrashed — soft-deleted port returns 404.
        $response = $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", ['name' => 'X']);
        $response->assertNotFound();
    }

    // --- Integrity: port_key historical identity ---

    public function test_soft_deleted_port_key_not_reusable_via_raw_db(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);

        // Create and soft-delete a port.
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'port_key' => 'PON-1']);
        $port->delete();

        // Raw INSERT another port with the same port_key on the same Asset.
        try {
            DB::table('network_ports')->insert([
                'asset_id' => $asset->id,
                'company_id' => $company->id,
                'port_key' => 'PON-1',
                'name' => 'Duplicate',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected QueryException for port_key uniqueness violation');
        } catch (\Exception $e) {
            $this->assertStringContainsString('network_ports_asset_port_key_unique', $e->getMessage());
        }
    }

    public function test_same_port_key_on_different_asset_allowed(): void
    {
        $company = Company::factory()->create();
        $assetA = $this->networkAsset($company);
        $assetB = $this->networkAsset($company);

        NetworkPort::factory()->create(['asset_id' => $assetA->id, 'company_id' => $company->id, 'port_key' => 'PON-1']);

        // Same port_key on a different Asset should succeed.
        $port = NetworkPort::factory()->create(['asset_id' => $assetB->id, 'company_id' => $company->id, 'port_key' => 'PON-1']);

        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'asset_id' => $assetB->id, 'port_key' => 'PON-1']);
    }

    // --- Integrity: Asset parent-history protection ---

    public function test_asset_category_cannot_change_away_from_network_with_port_history(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        try {
            $asset->update(['category' => 'INFRASTRUCTURE']);
            $this->fail('Expected QueryException for category protection');
        } catch (\Exception $e) {
            $this->assertStringContainsString('network port history', $e->getMessage());
        }
    }

    public function test_asset_category_cannot_change_away_from_network_even_when_all_ports_soft_deleted(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);
        $port->delete();

        try {
            $asset->update(['category' => 'INFRASTRUCTURE']);
            $this->fail('Expected QueryException for category protection');
        } catch (\Exception $e) {
            $this->assertStringContainsString('network port history', $e->getMessage());
        }
    }

    public function test_asset_company_reassignment_blocked_with_port_history(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $asset = $this->networkAsset($company);
        NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        try {
            $asset->update(['company_id' => $otherCompany->id]);
            $this->fail('Expected QueryException for company protection');
        } catch (\Exception $e) {
            $this->assertStringContainsString('network port history', $e->getMessage());
        }
    }

    public function test_asset_soft_delete_blocked_with_port_history(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        try {
            $asset->delete();
            $this->fail('Expected QueryException for asset deletion protection');
        } catch (\Exception $e) {
            $this->assertStringContainsString('network port history', $e->getMessage());
        }
    }

    public function test_asset_soft_delete_blocked_even_when_all_ports_soft_deleted(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);
        $port->delete();

        try {
            $asset->delete();
            $this->fail('Expected QueryException for asset deletion protection');
        } catch (\Exception $e) {
            $this->assertStringContainsString('network port history', $e->getMessage());
        }
    }

    public function test_unrelated_asset_updates_still_allowed(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        // These should succeed — they do not affect NetworkPort history integrity.
        $asset->update(['description' => 'Updated OLT', 'status' => 'MAINTENANCE']);
        $asset->update(['specifications' => ['chassis_slots' => 10]]);

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'description' => 'Updated OLT', 'status' => 'MAINTENANCE']);
    }

    public function test_asset_category_change_within_network_allowed(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company, 'OLT');
        NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        // Changing within NETWORK category (e.g., OLT -> SWITCH) should be allowed.
        $asset->update(['type' => 'SWITCH']);

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'type' => 'SWITCH']);
    }

    // --- PON Technology Classification Tests (NED-005a) ---

    public function test_null_technology_accepted(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'ETH1',
            'technology' => null,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('network_ports', ['asset_id' => $asset->id, 'port_key' => 'ETH1', 'technology' => null]);
    }

    public function test_technology_omitted_defaults_to_null(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'ETH2',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('network_ports', ['asset_id' => $asset->id, 'port_key' => 'ETH2', 'technology' => null]);
    }

    #[DataProvider('allowedTechnologies')]
    public function test_each_allowed_technology_accepted(string $technology): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => "PON-{$technology}",
            'technology' => $technology,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('network_ports', ['asset_id' => $asset->id, 'port_key' => "PON-{$technology}", 'technology' => $technology]);
    }

    public static function allowedTechnologies(): array
    {
        return array_map(fn ($t) => [$t], NetworkPort::TECHNOLOGIES);
    }

    public function test_invalid_technology_rejected_by_api(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);

        $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'BAD1',
            'technology' => 'invalid-tech',
        ])->assertUnprocessable();
    }

    public function test_invalid_technology_rejected_by_raw_db(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);

        try {
            DB::table('network_ports')->insert([
                'asset_id' => $asset->id,
                'company_id' => $company->id,
                'port_key' => 'BAD-RAW',
                'technology' => 'bad_value',
            ]);
            $this->fail('Raw DB insert with invalid technology should fail.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('network_ports_technology_check', $e->getMessage());
        }
    }

    public function test_technology_update_via_api(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'net.network-ports.update', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'technology' => null]);

        $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'technology' => 'xgs-pon',
        ])->assertOk();

        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'technology' => 'xgs-pon']);
    }

    public function test_technology_clear_via_api(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'net.network-ports.update', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'technology' => 'gpon']);

        $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'technology' => null,
        ])->assertOk();

        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'technology' => null]);
    }

    public function test_invalid_technology_update_rejected_by_api(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);

        $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'technology' => 'bad_value',
        ])->assertUnprocessable();
    }

    public function test_existing_non_pon_port_crud_unaffected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'net.network-ports.update', 'net.network-ports.delete', 'assets.view']);
        $asset = $this->networkAsset($company, 'SWITCH');

        // Create without technology
        $response = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'ETH0',
            'connector_type' => 'RJ45',
            'port_direction' => 'access',
        ]);
        $response->assertCreated();
        $portId = $response->json('data.id');

        // Read
        $this->actingAs($user)->getJson("/api/v1/fim/network-ports/{$portId}")->assertOk();

        // Update (non-technology field)
        $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$portId}", [
            'name' => 'Updated Ethernet',
        ])->assertOk();
        $this->assertDatabaseHas('network_ports', ['id' => $portId, 'name' => 'Updated Ethernet', 'technology' => null]);

        // Delete
        $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$portId}")->assertOk();
    }

    public function test_soft_deleted_port_technology_preserved(): void
    {
        $company = Company::factory()->create();
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'technology' => 'gpon']);

        $port->delete();

        $this->assertDatabaseHas('network_ports', ['id' => $port->id, 'technology' => 'gpon']);
        $this->assertSoftDeleted('network_ports', ['id' => $port->id]);
    }

    public function test_technology_in_resource_response(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'assets.view']);
        $asset = $this->networkAsset($company);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, 'technology' => 'gpon']);

        $response = $this->actingAs($user)->getJson("/api/v1/fim/network-ports/{$port->id}");
        $response->assertOk()->assertJsonPath('data.technology', 'gpon');
    }

    public function test_company_scope_rbac_unchanged_with_technology(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'net.network-ports.create', 'assets.view']);
        $asset = $this->networkAsset($company);
        $otherAsset = $this->networkAsset($otherCompany);

        // Create on own company
        $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/network-ports", [
            'port_key' => 'PON-OWN',
            'technology' => 'gpon',
        ])->assertCreated();

        // Create on other company should fail
        $this->actingAs($user)->postJson("/api/v1/assets/{$otherAsset->id}/network-ports", [
            'port_key' => 'PON-OTHER',
            'technology' => 'gpon',
        ])->assertForbidden();
    }
}
