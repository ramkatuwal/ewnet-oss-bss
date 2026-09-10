<?php

namespace Tests\Feature;

use App\Http\Resources\V1\AuditLogResource;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkPort;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\Site;
use App\Models\StaticRoute;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaticRouteTest extends TestCase
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
        foreach (['assets.view', 'net.routing-instances.view', 'net.routing-l3-interfaces.view', 'net.static-routes.view', 'net.static-routes.create', 'net.static-routes.delete'] as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function routingInstance(Asset $asset): RoutingInstance
    {
        return RoutingInstance::create(['asset_id' => $asset->id, 'company_id' => $asset->company_id, 'name' => 'default', 'kind' => 'default']);
    }

    private function interface(RoutingInstance $instance, string $kind): RoutingL3Interface
    {
        $attributes = ['routing_instance_id' => $instance->id, 'asset_id' => $instance->asset_id, 'company_id' => $instance->company_id, 'name' => $kind, 'kind' => $kind];
        if ($kind === 'physical') {
            $attributes['network_port_id'] = NetworkPort::factory()->create(['asset_id' => $instance->asset_id, 'company_id' => $instance->company_id])->id;
        }
        if ($kind === 'svi') {
            $attributes['vlan_id'] = Vlan::firstOrCreate(['company_id' => $instance->company_id, 'vid' => 10], ['name' => 'VLAN 10'])->id;
        }
        if ($kind === 'subinterface') {
            $port = NetworkPort::factory()->create(['asset_id' => $instance->asset_id, 'company_id' => $instance->company_id]);
            $parent = RoutingL3Interface::create(['routing_instance_id' => $instance->id, 'asset_id' => $instance->asset_id, 'company_id' => $instance->company_id, 'name' => 'parent', 'kind' => 'physical', 'network_port_id' => $port->id]);
            $attributes['vlan_id'] = Vlan::firstOrCreate(['company_id' => $instance->company_id, 'vid' => 11], ['name' => 'VLAN 11'])->id;
            $attributes['parent_routing_l3_interface_id'] = $parent->id;
        }

        return RoutingL3Interface::create($attributes);
    }

    public function test_routes_support_all_types_and_l3_interface_kinds(): void
    {
        $company = Company::factory()->create();
        $instance = $this->routingInstance($this->asset($company));
        $user = $this->user($company);
        foreach (['physical', 'svi', 'subinterface', 'loopback'] as $offset => $kind) {
            $type = ['forward', 'discard', 'reject'][$offset % 3];
            $payload = ['destination' => "10.{$offset}.0.0/24", 'route_type' => $type, 'routing_l3_interface_id' => $this->interface($instance, $kind)->id];
            if ($type === 'forward') {
                $payload['gateway'] = "10.{$offset}.0.1/32";
            }
            $this->actingAs($user)->postJson("/api/v1/routing-instances/{$instance->id}/static-routes", $payload)->assertCreated();
        }
        $this->actingAs($user)->postJson("/api/v1/routing-instances/{$instance->id}/static-routes", ['destination' => '2001:db8::/64', 'gateway' => '2001:db8::1/128', 'route_type' => 'forward'])->assertCreated();
        $this->actingAs($user)->getJson("/api/v1/routing-instances/{$instance->id}/static-routes")->assertOk()->assertJsonCount(5, 'data');
        $route = StaticRoute::where('destination', '10.0.0.0/24')->firstOrFail();
        $this->actingAs($user)->getJson("/api/v1/static-routes/{$route->id}")->assertOk()->assertJsonPath('data.destination', '10.0.0.0/24');
        $this->actingAs($user)->deleteJson("/api/v1/static-routes/{$route->id}")->assertOk();
        $metadata = AuditLog::where('action', 'net.static-route-created')->firstOrFail()->metadata;
        $this->assertArrayNotHasKey('destination', $metadata);
        $this->assertArrayNotHasKey('gateway', $metadata);
    }

    public function test_validation_scope_and_read_time_audit_redaction(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $instance = $this->routingInstance($this->asset($company));
        $user = $this->user($company);
        $otherInterface = $this->interface($this->routingInstance($this->asset($other)), 'loopback');
        $url = "/api/v1/routing-instances/{$instance->id}/static-routes";
        $this->actingAs($user)->postJson($url, ['destination' => '10.0.0.1/24', 'route_type' => 'forward'])->assertUnprocessable();
        $this->actingAs($user)->postJson($url, ['destination' => '10.0.0.0/24', 'gateway' => '10.0.0.1/24', 'route_type' => 'forward'])->assertUnprocessable();
        $this->actingAs($user)->postJson($url, ['destination' => '10.0.0.0/24', 'gateway' => '2001:db8::1/128', 'route_type' => 'forward'])->assertUnprocessable();
        $this->actingAs($user)->postJson($url, ['destination' => '10.0.0.0/24', 'route_type' => 'forward', 'routing_l3_interface_id' => $otherInterface->id])->assertUnprocessable();
        $route = StaticRoute::create(['routing_instance_id' => $instance->id, 'asset_id' => $instance->asset_id, 'company_id' => $company->id, 'destination' => '192.0.2.0/24', 'route_type' => 'discard']);
        $log = AuditLog::create(['action' => 'net.static-route-created', 'result' => 'success', 'metadata' => ['static_route_id' => $route->id, 'routing_instance_id' => $instance->id, 'asset_id' => $instance->asset_id, 'company_id' => $company->id]]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => User::factory()->create());
        $this->assertSame([], (new AuditLogResource($log))->toArray($request)['metadata']);
    }

    public function test_database_enforces_gateway_destination_ownership_history_and_parent_retirement(): void
    {
        $company = Company::factory()->create();
        $instance = $this->routingInstance($this->asset($company));
        $interface = $this->interface($instance, 'loopback');
        $route = StaticRoute::create(['routing_instance_id' => $instance->id, 'routing_l3_interface_id' => $interface->id, 'asset_id' => $instance->asset_id, 'company_id' => $company->id, 'destination' => '198.51.100.0/24', 'gateway' => '198.51.100.1/32', 'route_type' => 'forward']);
        $values = ['routing_instance_id' => $instance->id, 'asset_id' => $instance->asset_id, 'company_id' => $company->id, 'destination' => '203.0.113.0/24', 'route_type' => 'discard', 'created_at' => now(), 'updated_at' => now()];
        $this->expectQueryException(fn () => DB::table('static_routes')->insert(array_replace($values, ['gateway' => '203.0.113.1/24'])));
        $this->expectQueryException(fn () => DB::table('static_routes')->insert(array_replace($values, ['destination' => '203.0.113.1/24'])));
        $this->expectQueryException(fn () => DB::table('static_routes')->insert(array_replace($values, ['company_id' => $company->id + 1])));
        $this->expectQueryException(fn () => DB::table('static_routes')->where('id', $route->id)->update(['route_type' => 'reject']));
        $this->expectQueryException(fn () => DB::table('static_routes')->where('id', $route->id)->delete());
        $this->expectQueryException(fn () => DB::table('routing_l3_interfaces')->where('id', $interface->id)->update(['deleted_at' => now()]));
        $this->expectQueryException(fn () => DB::table('routing_instances')->where('id', $instance->id)->update(['deleted_at' => now()]));
        $route->delete();
        $this->expectQueryException(fn () => DB::table('static_routes')->where('id', $route->id)->update(['deleted_at' => null]));
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
