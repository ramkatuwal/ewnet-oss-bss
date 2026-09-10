<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoutingL3InterfaceRequest;
use App\Http\Resources\V1\RoutingL3InterfaceResource;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use App\Services\Network\RoutingL3InterfaceService;
use Illuminate\Http\Request;

class RoutingL3InterfaceController extends Controller
{
    public function __construct(protected RoutingL3InterfaceService $interfaces) {}

    public function index(Request $request, RoutingInstance $routingInstance)
    {
        $this->authorize('view', $routingInstance);
        $this->authorize('viewAny', RoutingL3Interface::class);

        return RoutingL3InterfaceResource::collection(ManagementScopeService::applyScopeToQuery(RoutingL3Interface::where('routing_instance_id', $routingInstance->id), $request->user(), RoutingL3Interface::class)->get()->filter(fn ($i) => $request->user()->can('view', $i))->values());
    }

    public function store(StoreRoutingL3InterfaceRequest $request, RoutingInstance $routingInstance)
    {
        $this->authorize('create', [RoutingL3Interface::class, $routingInstance]);
        $interface = $this->interfaces->create($routingInstance, $request->validated(), $request->user());
        AuditService::log('net.routing-l3-interface-created', 'success', $interface, $this->metadata($interface));

        return (new RoutingL3InterfaceResource($interface))->response()->setStatusCode(201);
    }

    public function show(RoutingL3Interface $routingL3Interface)
    {
        $this->authorize('view', $routingL3Interface);

        return new RoutingL3InterfaceResource($routingL3Interface);
    }

    public function destroy(Request $request, RoutingL3Interface $routingL3Interface)
    {
        $this->authorize('delete', $routingL3Interface);
        $metadata = $this->metadata($routingL3Interface);
        $this->interfaces->retire($routingL3Interface, $request->user());
        AuditService::log('net.routing-l3-interface-retired', 'success', $routingL3Interface, $metadata);

        return response()->json(['message' => 'Routing L3 interface retired successfully.']);
    }

    private function metadata(RoutingL3Interface $i): array
    {
        return $i->only(['id', 'routing_instance_id', 'asset_id', 'company_id', 'name', 'kind', 'network_port_id', 'vlan_id', 'parent_routing_l3_interface_id']) + ['routing_l3_interface_id' => $i->id];
    }
}
