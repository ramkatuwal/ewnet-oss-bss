<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePassiveOpticalPortRequest;
use App\Http\Requests\Api\V1\UpdatePassiveOpticalPortRequest;
use App\Http\Resources\V1\PassiveOpticalPortResource;
use App\Models\Asset;
use App\Models\PassiveOpticalPort;
use App\Services\AuditService;
use App\Services\Fim\PassiveOpticalPortService;
use Illuminate\Http\Request;

class PassiveOpticalPortController extends Controller
{
    public function __construct(protected PassiveOpticalPortService $ports) {}

    public function index(Request $request, Asset $asset)
    {
        $this->authorize('view', $asset);

        return PassiveOpticalPortResource::collection($asset->passiveOpticalPorts()->orderBy('port_number')->paginate($request->input('per_page', 15)));
    }

    public function store(StorePassiveOpticalPortRequest $request, Asset $asset)
    {
        $this->authorize('create', [PassiveOpticalPort::class, $asset]);
        $port = $this->ports->create($asset, $request->validated(), $request->user());
        AuditService::log('fim.passive-optical-port.created', 'success', $port, $this->auditMetadata($port));

        return (new PassiveOpticalPortResource($port))->response()->setStatusCode(201);
    }

    public function show(PassiveOpticalPort $passiveOpticalPort)
    {
        $this->authorize('view', $passiveOpticalPort);

        return new PassiveOpticalPortResource($passiveOpticalPort);
    }

    public function update(UpdatePassiveOpticalPortRequest $request, PassiveOpticalPort $passiveOpticalPort)
    {
        $this->authorize('update', $passiveOpticalPort);
        $port = $this->ports->update($passiveOpticalPort, $request->validated(), $request->user());
        AuditService::log('fim.passive-optical-port.updated', 'success', $port, [...$this->auditMetadata($port), 'metadata_changed' => array_key_exists('metadata', $request->validated())]);

        return new PassiveOpticalPortResource($port);
    }

    public function destroy(PassiveOpticalPort $passiveOpticalPort)
    {
        $this->authorize('delete', $passiveOpticalPort);
        $metadata = $this->auditMetadata($passiveOpticalPort);
        $this->ports->delete($passiveOpticalPort);
        AuditService::log('fim.passive-optical-port.deleted', 'success', $passiveOpticalPort, $metadata);

        return response()->json(['message' => 'Passive optical port deleted successfully.']);
    }

    protected function auditMetadata(PassiveOpticalPort $port): array
    {
        return $port->only(['id', 'asset_id', 'network_connection_point_id', 'company_id', 'port_number', 'connector_type', 'port_role']);
    }
}
