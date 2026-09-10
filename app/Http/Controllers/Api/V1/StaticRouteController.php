<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStaticRouteRequest;
use App\Http\Resources\V1\StaticRouteResource;
use App\Models\RoutingInstance;
use App\Models\StaticRoute;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use App\Services\Network\StaticRouteService;
use Illuminate\Http\Request;

class StaticRouteController extends Controller
{
    public function __construct(protected StaticRouteService $routes) {}

    public function index(Request $request, RoutingInstance $routingInstance)
    {
        $this->authorize('view', $routingInstance);
        $this->authorize('viewAny', StaticRoute::class);

        return StaticRouteResource::collection(ManagementScopeService::applyScopeToQuery(StaticRoute::where('routing_instance_id', $routingInstance->id), $request->user(), StaticRoute::class)->get()->filter(fn ($route) => $request->user()->can('view', $route))->values());
    }

    public function store(StoreStaticRouteRequest $request, RoutingInstance $routingInstance)
    {
        $this->authorize('create', [StaticRoute::class, $routingInstance]);
        $route = $this->routes->create($routingInstance, $request->validated(), $request->user());
        AuditService::log('net.static-route-created', 'success', $route, $this->metadata($route));

        return (new StaticRouteResource($route))->response()->setStatusCode(201);
    }

    public function show(StaticRoute $staticRoute)
    {
        $this->authorize('view', $staticRoute);

        return new StaticRouteResource($staticRoute);
    }

    public function destroy(Request $request, StaticRoute $staticRoute)
    {
        $this->authorize('delete', $staticRoute);
        $metadata = $this->metadata($staticRoute);
        $this->routes->retire($staticRoute, $request->user());
        AuditService::log('net.static-route-retired', 'success', $staticRoute, $metadata);

        return response()->json(['message' => 'Static route retired successfully.']);
    }

    private function metadata(StaticRoute $route): array
    {
        return $route->only(['id', 'routing_instance_id', 'routing_l3_interface_id', 'asset_id', 'company_id', 'route_type']) + ['static_route_id' => $route->id];
    }
}
