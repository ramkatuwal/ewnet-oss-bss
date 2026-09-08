<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePhysicalConnectionRequest;
use App\Http\Requests\Api\V1\UpdatePhysicalConnectionRequest;
use App\Models\FiberTermination;
use App\Models\PhysicalConnection;
use App\Services\AuditService;
use App\Services\Fim\PhysicalConnectionService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class PhysicalConnectionController extends Controller
{
    public function __construct(protected PhysicalConnectionService $connections) {}

    public function index(Request $r)
    {
        $this->authorize('viewAny', PhysicalConnection::class);

        return response()->json(['data' => PhysicalConnection::query()->whereIn('id', ManagementScopeService::applyScopeToQuery(PhysicalConnection::query(), $r->user(), PhysicalConnection::class)->pluck('id'))->orderByDesc('id')->paginate($r->input('per_page', 15))->items()]);
    }

    public function store(StorePhysicalConnectionRequest $r)
    {
        $this->authorize('create', PhysicalConnection::class);
        $a = FiberTermination::findOrFail($r->integer('termination_a_id'));
        $b = FiberTermination::findOrFail($r->integer('termination_b_id'));
        $this->authorize('view', $a);
        $this->authorize('view', $b);
        $c = $this->connections->create($r->validated(), $r->user());
        AuditService::log('fim.physical-connection.created', 'success', $c, $c->only(['id', 'company_id', 'termination_a_id', 'termination_b_id', 'connection_type']));

        return response()->json(['data' => $c], 201);
    }

    public function show(PhysicalConnection $physicalConnection)
    {
        $this->authorize('view', $physicalConnection);

        return ['data' => $physicalConnection];
    }

    public function update(UpdatePhysicalConnectionRequest $r, PhysicalConnection $physicalConnection)
    {
        $this->authorize('update', $physicalConnection);
        $c = $this->connections->update($physicalConnection, $r->validated(), $r->user());
        AuditService::log('fim.physical-connection.updated', 'success', $c, [...$c->only(['id', 'company_id', 'termination_a_id', 'termination_b_id', 'connection_type']), 'metadata_changed' => array_key_exists('metadata', $r->validated())]);

        return ['data' => $c];
    }

    public function destroy(PhysicalConnection $physicalConnection)
    {
        $this->authorize('delete', $physicalConnection);
        $m = $physicalConnection->only(['id', 'company_id', 'termination_a_id', 'termination_b_id', 'connection_type']);
        $this->connections->delete($physicalConnection);
        AuditService::log('fim.physical-connection.disconnected', 'success', $physicalConnection, $m);

        return response()->json(['message' => 'Physical connection disconnected successfully.']);
    }
}
