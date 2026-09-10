<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoutingL3InterfaceAddressRequest;
use App\Http\Resources\V1\RoutingL3InterfaceAddressResource;
use App\Models\RoutingL3Interface;
use App\Models\RoutingL3InterfaceAddress;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use App\Services\Network\RoutingL3InterfaceAddressService;
use Illuminate\Http\Request;

class RoutingL3InterfaceAddressController extends Controller
{
    public function __construct(protected RoutingL3InterfaceAddressService $addresses) {}

    public function index(Request $request, RoutingL3Interface $routingL3Interface)
    {
        $this->authorize('view', $routingL3Interface);
        $this->authorize('viewAny', RoutingL3InterfaceAddress::class);

        return RoutingL3InterfaceAddressResource::collection(ManagementScopeService::applyScopeToQuery(RoutingL3InterfaceAddress::where('routing_l3_interface_id', $routingL3Interface->id), $request->user(), RoutingL3InterfaceAddress::class)->get()->filter(fn ($address) => $request->user()->can('view', $address))->values());
    }

    public function store(StoreRoutingL3InterfaceAddressRequest $request, RoutingL3Interface $routingL3Interface)
    {
        $this->authorize('create', [RoutingL3InterfaceAddress::class, $routingL3Interface]);
        $address = $this->addresses->create($routingL3Interface, $request->validated(), $request->user());
        AuditService::log('net.routing-l3-interface-address-created', 'success', $address, $this->metadata($address));

        return (new RoutingL3InterfaceAddressResource($address))->response()->setStatusCode(201);
    }

    public function show(RoutingL3InterfaceAddress $routingL3InterfaceAddress)
    {
        $this->authorize('view', $routingL3InterfaceAddress);

        return new RoutingL3InterfaceAddressResource($routingL3InterfaceAddress);
    }

    public function destroy(Request $request, RoutingL3InterfaceAddress $routingL3InterfaceAddress)
    {
        $this->authorize('delete', $routingL3InterfaceAddress);
        $metadata = $this->metadata($routingL3InterfaceAddress);
        $this->addresses->retire($routingL3InterfaceAddress, $request->user());
        AuditService::log('net.routing-l3-interface-address-retired', 'success', $routingL3InterfaceAddress, $metadata);

        return response()->json(['message' => 'Routing L3 interface address retired successfully.']);
    }

    private function metadata(RoutingL3InterfaceAddress $address): array
    {
        return $address->only(['id', 'routing_l3_interface_id', 'routing_instance_id', 'asset_id', 'company_id', 'address_role']) + ['routing_l3_interface_address_id' => $address->id];
    }
}
