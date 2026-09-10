<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\NetworkPort;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutingL3InterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asset(Company $company): Asset
    {
        return Asset::factory()->create(['company_id' => $company->id, 'site_id' => Site::factory()->create(['company_id' => $company->id])->id, 'category' => 'NETWORK']);
    }

    private function user(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        foreach (['assets.view', 'net.routing-instances.view', 'net.routing-l3-interfaces.view', 'net.routing-l3-interfaces.create', 'net.routing-l3-interfaces.delete', 'net.network-ports.view', 'net.vlans.view'] as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function routingInstance(Asset $asset): RoutingInstance
    {
        return RoutingInstance::create(['asset_id' => $asset->id, 'company_id' => $asset->company_id, 'name' => 'default', 'kind' => 'default']);
    }

    public function test_authoritative_shapes_create_list_show_and_retire(): void
    {
        $company = Company::factory()->create();
        $asset = $this->asset($company);
        $user = $this->user($company);
        $instance = $this->routingInstance($asset);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);
        $vlan = Vlan::create(['company_id' => $company->id, 'vid' => 10, 'name' => 'VLAN 10']);
        $physical = $this->actingAs($user)->postJson("/api/v1/routing-instances/{$instance->id}/l3-interfaces", ['name' => 'xe-0/0/0', 'kind' => 'physical', 'network_port_id' => $port->id])->assertCreated()->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/routing-instances/{$instance->id}/l3-interfaces", ['name' => 'vlan10', 'kind' => 'svi', 'vlan_id' => $vlan->id])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/routing-instances/{$instance->id}/l3-interfaces", ['name' => 'xe-0/0/0.10', 'kind' => 'subinterface', 'parent_routing_l3_interface_id' => $physical, 'vlan_id' => $vlan->id])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/routing-instances/{$instance->id}/l3-interfaces", ['name' => 'lo0', 'kind' => 'loopback'])->assertCreated();
        $this->actingAs($user)->getJson("/api/v1/routing-instances/{$instance->id}/l3-interfaces")->assertOk()->assertJsonCount(4, 'data');
        $this->actingAs($user)->getJson("/api/v1/routing-l3-interfaces/{$physical}")->assertOk()->assertJsonPath('data.kind', 'physical');
        $this->actingAs($user)->deleteJson("/api/v1/routing-l3-interfaces/{$physical}")->assertUnprocessable();
    }

    public function test_database_enforces_shape_parent_company_and_history(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $asset = $this->asset($company);
        $instance = $this->routingInstance($asset);
        $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id]);
        $vlan = Vlan::create(['company_id' => $company->id, 'vid' => 10, 'name' => 'VLAN 10']);
        $this->expectQueryException(fn () => DB::table('routing_l3_interfaces')->insert(['routing_instance_id' => $instance->id, 'asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'bad', 'kind' => 'physical', 'created_at' => now(), 'updated_at' => now()]));
        $physical = RoutingL3Interface::create(['routing_instance_id' => $instance->id, 'asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'p', 'kind' => 'physical', 'network_port_id' => $port->id]);
        $svi = RoutingL3Interface::create(['routing_instance_id' => $instance->id, 'asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'svi', 'kind' => 'svi', 'vlan_id' => $vlan->id]);
        $this->expectQueryException(fn () => DB::table('routing_l3_interfaces')->where('id', $physical->id)->update(['name' => 'changed']));
        $this->expectQueryException(fn () => DB::table('network_ports')->where('id', $port->id)->update(['company_id' => $other->id]));
        $this->expectQueryException(fn () => DB::table('routing_l3_interfaces')->insert(['routing_instance_id' => $instance->id, 'asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'bad-parent', 'kind' => 'subinterface', 'vlan_id' => $vlan->id, 'parent_routing_l3_interface_id' => $svi->id, 'created_at' => now(), 'updated_at' => now()]));
    }

    private function expectQueryException(callable $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected database integrity violation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
