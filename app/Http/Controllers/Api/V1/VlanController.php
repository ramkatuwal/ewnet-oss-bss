<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVlanRequest;
use App\Http\Requests\Api\V1\UpdateVlanRequest;
use App\Http\Resources\V1\VlanResource;
use App\Models\Vlan;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use App\Services\Network\VlanService;
use Illuminate\Http\Request;

class VlanController extends Controller
{
    public function __construct(protected VlanService $vlans) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Vlan::class);
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);

        $vlans = ManagementScopeService::applyScopeToQuery(Vlan::query(), $request->user(), Vlan::class)
            ->orderBy('vid')
            ->paginate($request->integer('per_page', 15));

        return VlanResource::collection($vlans);
    }

    public function store(StoreVlanRequest $request)
    {
        $this->authorize('create', Vlan::class);
        $vlan = $this->vlans->create($request->validated(), $request->user());
        AuditService::log('net.vlan.created', 'success', $vlan, $this->auditMetadata($vlan));

        return (new VlanResource($vlan))->response()->setStatusCode(201);
    }

    public function show(Vlan $vlan)
    {
        $this->authorize('view', $vlan);

        return new VlanResource($vlan);
    }

    public function update(UpdateVlanRequest $request, Vlan $vlan)
    {
        $this->authorize('update', $vlan);
        $vlan = $this->vlans->update($vlan, $request->validated(), $request->user());
        AuditService::log('net.vlan.updated', 'success', $vlan, $this->auditMetadata($vlan));

        return new VlanResource($vlan);
    }

    public function destroy(Request $request, Vlan $vlan)
    {
        $this->authorize('delete', $vlan);
        $metadata = $this->auditMetadata($vlan);
        $this->vlans->delete($vlan, $request->user());
        AuditService::log('net.vlan.deleted', 'success', $vlan, $metadata);

        return response()->json(['message' => 'VLAN retired successfully.']);
    }

    private function auditMetadata(Vlan $vlan): array
    {
        return [
            'vlan_id' => $vlan->id,
            'company_id' => $vlan->company_id,
            'vid' => $vlan->vid,
            'name' => $vlan->name,
            'reserved' => $vlan->reserved,
        ];
    }
}
