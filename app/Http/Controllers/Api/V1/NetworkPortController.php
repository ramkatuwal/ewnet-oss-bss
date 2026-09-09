<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNetworkPortRequest;
use App\Http\Requests\Api\V1\UpdateNetworkPortRequest;
use App\Http\Resources\V1\NetworkPortResource;
use App\Models\Asset;
use App\Models\NetworkPort;
use App\Services\AuditService;
use App\Services\Network\NetworkPortService;
use Illuminate\Http\Request;

class NetworkPortController extends Controller
{
    public function __construct(protected NetworkPortService $ports) {}

    public function index(Request $request, Asset $asset)
    {
        $this->authorize('view', $asset);

        return NetworkPortResource::collection(
            $asset->networkPorts()->orderBy('slot')->orderBy('card')->orderBy('port_number')->paginate($request->input('per_page', 15))
        );
    }

    public function store(StoreNetworkPortRequest $request, Asset $asset)
    {
        $this->authorize('create', [NetworkPort::class, $asset]);
        $port = $this->ports->create($asset, $request->validated(), $request->user());
        AuditService::log('network-port.created', 'success', $port, $this->auditMetadata($port));

        return (new NetworkPortResource($port))->response()->setStatusCode(201);
    }

    public function show(NetworkPort $networkPort)
    {
        $this->authorize('view', $networkPort);

        return new NetworkPortResource($networkPort);
    }

    public function update(UpdateNetworkPortRequest $request, NetworkPort $networkPort)
    {
        $this->authorize('update', $networkPort);
        $port = $this->ports->update($networkPort, $request->validated(), $request->user());
        AuditService::log('network-port.updated', 'success', $port, $this->auditMetadata($port));

        return new NetworkPortResource($port);
    }

    public function destroy(NetworkPort $networkPort)
    {
        $this->authorize('delete', $networkPort);
        $metadata = $this->auditMetadata($networkPort);
        $this->ports->delete($networkPort);
        AuditService::log('network-port.deleted', 'success', $networkPort, $metadata);

        return response()->json(['message' => 'Network port deleted successfully.']);
    }

    protected function auditMetadata(NetworkPort $port): array
    {
        return $port->only(['id', 'asset_id', 'company_id', 'port_key', 'connector_type', 'port_direction']);
    }
}
