<?php

namespace Tests\Feature;

use App\Http\Resources\V1\AuditLogResource;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkPort;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\RoutingL3InterfaceAddress;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutingL3InterfaceAddressTest extends TestCase
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
        foreach (['assets.view', 'net.routing-instances.view', 'net.routing-l3-interfaces.view', 'net.routing-l3-interface-addresses.view', 'net.routing-l3-interface-addresses.create', 'net.routing-l3-interface-addresses.delete'] as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function interface(Asset $asset, string $kind = 'loopback'): RoutingL3Interface
    {
        $instance = RoutingInstance::create(['asset_id' => $asset->id, 'company_id' => $asset->company_id, 'name' => "{$kind}-ri", 'kind' => 'default']);
        $attributes = ['routing_instance_id' => $instance->id, 'asset_id' => $asset->id, 'company_id' => $asset->company_id, 'name' => $kind, 'kind' => $kind];
        if ($kind === 'physical') {
            $attributes['network_port_id'] = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $asset->company_id])->id;
        } elseif ($kind === 'svi') {
            $attributes['vlan_id'] = Vlan::firstOrCreate(['company_id' => $asset->company_id, 'vid' => 10], ['name' => 'VLAN 10'])->id;
        } elseif ($kind === 'subinterface') {
            $port = NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $asset->company_id]);
            $parent = RoutingL3Interface::create(['routing_instance_id' => $instance->id, 'asset_id' => $asset->id, 'company_id' => $asset->company_id, 'name' => 'parent', 'kind' => 'physical', 'network_port_id' => $port->id]);
            $attributes['vlan_id'] = Vlan::firstOrCreate(['company_id' => $asset->company_id, 'vid' => 10], ['name' => 'VLAN 10'])->id;
            $attributes['parent_routing_l3_interface_id'] = $parent->id;
        }

        return RoutingL3Interface::create($attributes);
    }

    public function test_authoritative_addresses_are_available_for_every_l3_kind(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        foreach (['physical', 'svi', 'subinterface', 'loopback'] as $offset => $kind) {
            $interface = $this->interface($this->asset($company), $kind);
            $this->actingAs($user)->postJson("/api/v1/routing-l3-interfaces/{$interface->id}/addresses", ['address' => '10.0.'.$offset.'.1/24', 'address_role' => 'primary'])
                ->assertCreated()->assertJsonPath('data.routing_l3_interface_id', $interface->id);
        }
        $metadata = AuditLog::where('action', 'net.routing-l3-interface-address-created')->firstOrFail()->metadata;
        $this->assertArrayNotHasKey('address', $metadata);
        $this->assertArrayNotHasKey('prefix_length', $metadata);
    }

    public function test_audit_resource_redacts_hidden_authoritative_address_identifiers(): void
    {
        $company = Company::factory()->create();
        $interface = $this->interface($this->asset($company));
        $address = RoutingL3InterfaceAddress::create(['routing_l3_interface_id' => $interface->id, 'routing_instance_id' => $interface->routing_instance_id, 'asset_id' => $interface->asset_id, 'company_id' => $company->id, 'address' => '10.0.0.1/24', 'prefix_length' => 24, 'address_role' => 'primary']);
        $log = AuditLog::create(['action' => 'net.routing-l3-interface-address-created', 'result' => 'success', 'metadata' => ['routing_l3_interface_address_id' => $address->id, 'routing_l3_interface_id' => $address->routing_l3_interface_id, 'routing_instance_id' => $address->routing_instance_id, 'asset_id' => $address->asset_id, 'company_id' => $address->company_id]]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => User::factory()->create());
        $metadata = (new AuditLogResource($log))->toArray($request)['metadata'];
        $this->assertSame([], $metadata);
    }

    public function test_address_requires_a_valid_prefix_and_enforces_host_and_primary_rules(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company);
        $interface = $this->interface($this->asset($company));
        $other = RoutingL3Interface::create(['routing_instance_id' => $interface->routing_instance_id, 'asset_id' => $interface->asset_id, 'company_id' => $interface->company_id, 'name' => 'lo1', 'kind' => 'loopback']);
        $base = "/api/v1/routing-l3-interfaces/{$interface->id}/addresses";
        $this->actingAs($user)->postJson($base, ['address' => '10.0.0.1', 'address_role' => 'primary'])->assertUnprocessable();
        $this->actingAs($user)->postJson($base, ['address' => '10.0.0.1/24', 'address_role' => 'primary'])->assertCreated();
        $this->actingAs($user)->postJson($base, ['address' => '10.0.0.2/24', 'address_role' => 'primary'])->assertUnprocessable();
        $this->actingAs($user)->postJson("/api/v1/routing-l3-interfaces/{$other->id}/addresses", ['address' => '10.0.0.1/25', 'address_role' => 'secondary'])->assertUnprocessable();
        $differentInstance = $this->interface($this->asset($company));
        $this->actingAs($user)->postJson("/api/v1/routing-l3-interfaces/{$differentInstance->id}/addresses", ['address' => '10.0.0.1/25', 'address_role' => 'secondary'])->assertCreated();
    }

    public function test_database_enforces_denormalized_ownership_immutability_and_parent_retirement(): void
    {
        $company = Company::factory()->create();
        $interface = $this->interface($this->asset($company));
        $address = RoutingL3InterfaceAddress::create(['routing_l3_interface_id' => $interface->id, 'routing_instance_id' => $interface->routing_instance_id, 'asset_id' => $interface->asset_id, 'company_id' => $interface->company_id, 'address' => '2001:db8::1/64', 'prefix_length' => 64, 'address_role' => 'primary']);
        $this->expectQueryException(fn () => DB::table('routing_l3_interface_addresses')->insert(['routing_l3_interface_id' => $interface->id, 'routing_instance_id' => $interface->routing_instance_id, 'asset_id' => $interface->asset_id, 'company_id' => $interface->company_id, 'address' => '10.0.0.2/24', 'address_role' => 'secondary', 'created_at' => now(), 'updated_at' => now()]));
        $this->expectQueryException(fn () => DB::table('routing_l3_interface_addresses')->insert(['routing_l3_interface_id' => $interface->id, 'routing_instance_id' => $interface->routing_instance_id, 'asset_id' => $interface->asset_id, 'company_id' => $interface->company_id + 1, 'address' => '10.0.0.2/24', 'prefix_length' => 24, 'address_role' => 'secondary', 'created_at' => now(), 'updated_at' => now()]));
        $this->expectQueryException(fn () => DB::table('routing_l3_interface_addresses')->where('id', $address->id)->update(['address_role' => 'secondary']));
        $this->expectQueryException(fn () => DB::table('routing_l3_interface_addresses')->where('id', $address->id)->delete());
        $this->expectQueryException(fn () => DB::table('routing_l3_interfaces')->where('id', $interface->id)->update(['deleted_at' => now()]));
        $address->delete();
        $this->expectQueryException(fn () => DB::table('routing_l3_interface_addresses')->where('id', $address->id)->update(['deleted_at' => null]));
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
