<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoutingInstanceRequest;
use App\Http\Resources\V1\RoutingInstanceResource;
use App\Models\Asset;
use App\Models\RoutingInstance;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use App\Services\Network\RoutingInstanceService;
use Illuminate\Http\Request;

class RoutingInstanceController extends Controller
{
    public function __construct(protected RoutingInstanceService $routingInstances) {}

    public function index(Request $request, Asset $asset)
    {
        $this->authorize('view', $asset);
        $this->authorize('viewAny', RoutingInstance::class);
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        $instances = ManagementScopeService::applyScopeToQuery(
            RoutingInstance::where('asset_id', $asset->id)->with('asset'), $request->user(), RoutingInstance::class
        )->get()->filter(fn (RoutingInstance $instance) => $request->user()->can('view', $instance))->values();

        return RoutingInstanceResource::collection($instances);
    }

    public function store(StoreRoutingInstanceRequest $request, Asset $asset)
    {
        $this->authorize('create', [RoutingInstance::class, $asset]);
        $routingInstance = $this->routingInstances->create($asset, $request->validated(), $request->user());
        AuditService::log('net.routing-instance-created', 'success', $routingInstance, $this->auditMetadata($routingInstance));

        return (new RoutingInstanceResource($routingInstance))->response()->setStatusCode(201);
    }

    public function show(RoutingInstance $routingInstance)
    {
        $this->authorize('view', $routingInstance);

        return new RoutingInstanceResource($routingInstance);
    }

    public function destroy(Request $request, RoutingInstance $routingInstance)
    {
        $this->authorize('delete', $routingInstance);
        $metadata = $this->auditMetadata($routingInstance);
        $this->routingInstances->retire($routingInstance, $request->user());
        AuditService::log('net.routing-instance-retired', 'success', $routingInstance, $metadata);

        return response()->json(['message' => 'Routing instance retired successfully.']);
    }

    private function auditMetadata(RoutingInstance $routingInstance): array
    {
        return $routingInstance->only(['id', 'asset_id', 'company_id', 'name', 'kind']) + ['routing_instance_id' => $routingInstance->id];
    }
}
