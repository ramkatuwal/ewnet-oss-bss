<?php

namespace Tests\Feature\Fim;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassiveOpticalPortApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array{0: Asset, 1: NetworkConnectionPoint} */
    protected function endpoint(Company $company, string $type = 'ODF'): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => $type]);
        $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);

        return [$asset, $point];
    }

    protected function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);

        return $user->refresh();
    }

    protected function permissions(string ...$port): array
    {
        return [...$port, 'assets.view', 'fim.connection-points.view'];
    }

    public function test_factory_creates_a_valid_passive_endpoint(): void
    {
        $port = PassiveOpticalPort::factory()->create();

        $this->assertSame($port->asset_id, $port->networkConnectionPoint->asset_id);
        $this->assertSame($port->company_id, $port->asset->company_id);
    }

    public function test_nested_routes_create_and_list_ports_with_route_derived_asset_and_company(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $user = $this->user($company, $this->permissions('fim.passive-optical-ports.create'));

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/passive-optical-ports", [
            'asset_id' => 999999,
            'company_id' => 999999,
            'network_connection_point_id' => $point->id,
            'port_number' => 'PON-01',
            'connector_type' => 'SC/APC',
            'port_role' => 'generic',
            'metadata' => ['survey_note' => 'not in audit'],
        ]);

        $response->assertCreated()->assertJsonPath('data.asset_id', $asset->id)->assertJsonPath('data.company_id', $company->id)->assertJsonPath('data.port_number', 'PON-01');
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/assets/{$asset->id}/passive-optical-ports")->assertOk()->assertJsonPath('data.0.id', $response->json('data.id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'fim.passive-optical-port.created', 'target_id' => $response->json('data.id')]);
        $this->assertNotContains('not in audit', AuditLog::where('action', 'fim.passive-optical-port.created')->firstOrFail()->metadata);
    }

    public function test_rejects_foreign_company_and_inactive_connection_points_but_allows_an_ncp_on_another_asset(): void
    {
        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        [$asset, $point] = $this->endpoint($owner, 'ODF');
        [, $foreignPoint] = $this->endpoint($other);
        [, $otherPoint] = $this->endpoint($owner);
        $user = $this->user($owner, $this->permissions('fim.passive-optical-ports.create'));
        $payload = ['network_connection_point_id' => $point->id, 'port_number' => 'PON-01', 'port_role' => 'generic'];

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/passive-optical-ports", [...$payload, 'network_connection_point_id' => $foreignPoint->id])->assertUnprocessable()->assertJsonValidationErrors('network_connection_point_id');
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/passive-optical-ports", [...$payload, 'network_connection_point_id' => $otherPoint->id])->assertCreated();
        $point->delete();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/passive-optical-ports", [...$payload, 'port_number' => 'PON-02'])->assertUnprocessable()->assertJsonValidationErrors('network_connection_point_id');
    }

    public function test_same_ncp_allows_multiple_ports_while_asset_port_numbers_remain_unique(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company, 'SPLITTER');
        $user = $this->user($company, $this->permissions('fim.passive-optical-ports.create'));
        $endpoint = "/api/v1/assets/{$asset->id}/passive-optical-ports";

        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['network_connection_point_id' => $point->id, 'port_number' => 'PON-01', 'port_role' => 'splitter_input'])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['network_connection_point_id' => $point->id, 'port_number' => 'PON-02', 'port_role' => 'splitter_output'])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['network_connection_point_id' => $point->id, 'port_number' => 'PON-01', 'port_role' => 'generic'])->assertUnprocessable()->assertJsonValidationErrors('port_number');
    }

    public function test_standalone_show_update_and_delete_only_allow_mutable_approved_fields(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $user = $this->user($company, $this->permissions('fim.passive-optical-ports.create', 'fim.passive-optical-ports.view', 'fim.passive-optical-ports.update', 'fim.passive-optical-ports.delete'));
        $id = $this->actingAs($user, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/passive-optical-ports", ['network_connection_point_id' => $point->id, 'port_number' => 'PON-01', 'port_role' => 'generic'])->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/passive-optical-ports/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/passive-optical-ports/{$id}", ['port_number' => 'PON-02'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/passive-optical-ports/{$id}", ['port_role' => 'splitter_output'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/passive-optical-ports/{$id}", ['connector_type' => 'LC/APC', 'metadata' => ['survey' => 'corrected']])->assertOk()->assertJsonPath('data.connector_type', 'LC/APC');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/passive-optical-ports/{$id}")->assertOk();
    }
}
