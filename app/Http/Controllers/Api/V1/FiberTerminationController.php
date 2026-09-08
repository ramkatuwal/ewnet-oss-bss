<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFiberTerminationRequest;
use App\Http\Requests\Api\V1\UpdateFiberTerminationRequest;
use App\Http\Resources\V1\FiberTerminationResource;
use App\Models\FiberCore;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Services\AuditService;
use App\Services\Fim\FiberTerminationService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class FiberTerminationController extends Controller
{
    public function __construct(protected FiberTerminationService $terminations) {}

    public function index(Request $request, FiberCore $fiberCore)
    {
        $this->authorize('viewAny', FiberTermination::class);
        $this->authorize('view', $fiberCore);

        return FiberTerminationResource::collection($fiberCore->terminations()->orderBy('segment_end')->paginate($request->input('per_page', 15)));
    }

    public function store(StoreFiberTerminationRequest $request, FiberCore $fiberCore)
    {
        $this->authorize('create', FiberTermination::class);
        $this->authorize('create', $fiberCore);
        $this->authorize('create', $fiberCore->fiberSegment);
        abort_unless(ManagementScopeService::isInScope($request->user(), $fiberCore), 403);
        $point = NetworkConnectionPoint::findOrFail($request->validated('network_connection_point_id'));
        $this->authorize('view', $point);
        $termination = $this->terminations->create($fiberCore, $request->validated(), $request->user());
        AuditService::log('fim.fiber-termination.created', 'success', $termination, $this->auditMetadata($termination));

        return (new FiberTerminationResource($termination))->response()->setStatusCode(201);
    }

    public function show(FiberTermination $fiberTermination)
    {
        $this->authorize('view', $fiberTermination);

        return new FiberTerminationResource($fiberTermination);
    }

    public function update(UpdateFiberTerminationRequest $request, FiberTermination $fiberTermination)
    {
        $this->authorize('update', $fiberTermination);
        if ($request->filled('network_connection_point_id')) {
            $this->authorize('view', NetworkConnectionPoint::findOrFail($request->integer('network_connection_point_id')));
        }
        $before = $this->auditMetadata($fiberTermination);
        $termination = $this->terminations->update($fiberTermination, $request->validated(), $request->user());
        AuditService::log('fim.fiber-termination.updated', 'success', $termination, [
            'before' => $before,
            'after' => $this->auditMetadata($termination),
            'metadata_changed' => array_key_exists('metadata', $request->validated()),
        ]);

        return new FiberTerminationResource($termination);
    }

    public function destroy(FiberTermination $fiberTermination)
    {
        $this->authorize('delete', $fiberTermination);
        $metadata = $this->auditMetadata($fiberTermination);
        $this->terminations->delete($fiberTermination);
        AuditService::log('fim.fiber-termination.deleted', 'success', $fiberTermination, $metadata);

        return response()->json(['message' => 'Fiber termination deleted successfully.']);
    }

    protected function auditMetadata(FiberTermination $termination): array
    {
        return $termination->only(['id', 'fiber_core_id', 'segment_end', 'network_connection_point_id', 'company_id']);
    }
}
