<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFiberSegmentRequest;
use App\Http\Requests\Api\V1\UpdateFiberSegmentRequest;
use App\Http\Resources\V1\FiberSegmentResource;
use App\Models\FiberSegment;
use App\Services\AuditService;
use App\Services\Fim\FiberCableService;
use App\Services\Fim\FiberSegmentService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FiberSegmentController extends Controller
{
    public function __construct(protected FiberSegmentService $segments) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FiberSegment::class);

        $query = FiberSegment::query()
            ->select('fiber_segments.*')
            ->selectRaw('ST_AsGeoJSON(fiber_segments.geometry) AS geometry_geojson')
            ->selectRaw('ST_Length(fiber_segments.geometry::geography) AS calculated_length_meters')
            ->with(['fiberCable', 'endpointA', 'endpointB']);
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), FiberSegment::class);

        foreach (['fiber_cable_id', 'endpoint_a_id', 'endpoint_b_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $segments = $query->orderBy('fiber_cable_id')->orderBy('sequence')->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => FiberSegmentResource::collection($segments->items()),
            'meta' => [
                'current_page' => $segments->currentPage(),
                'last_page' => $segments->lastPage(),
                'per_page' => $segments->perPage(),
                'total' => $segments->total(),
            ],
        ]);
    }

    public function store(StoreFiberSegmentRequest $request)
    {
        $this->authorize('create', FiberSegment::class);
        $segment = $this->segments->create($request->validated(), $request->user());
        AuditService::log('fim.fiber-segment.created', 'success', $segment, $this->safeAuditMetadata($request->validated()));

        return (new FiberSegmentResource($segment->load(['fiberCable', 'endpointA', 'endpointB'])))->response()->setStatusCode(201);
    }

    public function show(FiberSegment $fiberSegment)
    {
        $this->authorize('view', $fiberSegment);
        $segment = $this->segments->findWithGeoJson($fiberSegment->id);

        return new FiberSegmentResource($segment->load(['fiberCable', 'endpointA', 'endpointB']));
    }

    public function update(UpdateFiberSegmentRequest $request, FiberSegment $fiberSegment)
    {
        $this->authorize('update', $fiberSegment);
        $oldGeometry = $this->geometrySummary($fiberSegment->id);
        $segment = $this->segments->update($fiberSegment, $request->validated(), $request->user());
        $metadata = $this->safeAuditMetadata($request->validated());
        if (array_key_exists('geometry', $request->validated())) {
            $metadata['geometry'] = [
                'old' => $oldGeometry,
                'new' => FiberSegmentService::geometrySummary($request->validated()['geometry']),
            ];
        }
        AuditService::log('fim.fiber-segment.updated', 'success', $segment, $metadata);

        return new FiberSegmentResource($segment->load(['fiberCable', 'endpointA', 'endpointB']));
    }

    public function destroy(FiberSegment $fiberSegment)
    {
        $this->authorize('delete', $fiberSegment);
        $geometry = $this->geometrySummary($fiberSegment->id);
        $this->segments->delete($fiberSegment);
        $parentRoute = DB::selectOne(
            'SELECT route_geometry_authority, route_geometry IS NOT NULL AS available FROM fiber_cables WHERE id = ?',
            [$fiberSegment->fiber_cable_id]
        );
        AuditService::log('fim.fiber-segment.deleted', 'success', $fiberSegment, [
            'fiber_cable_id' => $fiberSegment->fiber_cable_id,
            'endpoint_a_id' => $fiberSegment->endpoint_a_id,
            'endpoint_b_id' => $fiberSegment->endpoint_b_id,
            'geometry' => $geometry,
            'parent_route' => [
                'authority' => $parentRoute->route_geometry_authority,
                'available' => (bool) $parentRoute->available,
            ],
        ]);

        return response()->json(['message' => 'Fiber segment deleted successfully.']);
    }

    protected function safeAuditMetadata(array $validated): array
    {
        $metadata = array_intersect_key($validated, array_flip([
            'fiber_cable_id', 'endpoint_a_id', 'endpoint_b_id', 'sequence', 'length_meters', 'status', 'metadata',
        ]));
        if (array_key_exists('geometry', $validated)) {
            $metadata['geometry'] = FiberSegmentService::geometrySummary($validated['geometry']);
        }

        return $metadata;
    }

    protected function geometrySummary(int $id): ?array
    {
        $segment = $this->segments->findWithGeoJson($id);
        $geometry = FiberCableService::decodeRouteGeoJson($segment->getAttribute('geometry_geojson'));

        return $geometry === null ? null : FiberSegmentService::geometrySummary($geometry);
    }
}
