<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFiberCableRequest;
use App\Http\Requests\Api\V1\UpdateFiberCableRequest;
use App\Http\Resources\V1\FiberCableResource;
use App\Models\FiberCable;
use App\Services\AuditService;
use App\Services\Fim\FiberCableService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class FiberCableController extends Controller
{
    public function __construct(protected FiberCableService $cables) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FiberCable::class);

        $query = FiberCable::query()
            ->select('fiber_cables.*')
            ->selectRaw('ST_AsGeoJSON(fiber_cables.route_geometry) AS route_geojson')
            ->with(['company', 'startSite', 'endSite']);
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), FiberCable::class);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('cable_code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }
        foreach (['company_id', 'status', 'cable_type', 'start_site_id', 'end_site_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $query->orderBy('created_at', 'desc');
        $cables = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => FiberCableResource::collection($cables->items()),
            'meta' => [
                'current_page' => $cables->currentPage(),
                'last_page' => $cables->lastPage(),
                'per_page' => $cables->perPage(),
                'total' => $cables->total(),
            ],
        ]);
    }

    public function store(StoreFiberCableRequest $request)
    {
        $this->authorize('create', FiberCable::class);

        $cable = $this->cables->create($request->validated(), $request->user());

        AuditService::log('fim.cable.created', 'success', $cable, $this->safeAuditMetadata($request->validated()));

        return (new FiberCableResource($cable->load(['company', 'startSite', 'endSite'])))->response()->setStatusCode(201);
    }

    public function show(FiberCable $fiberCable)
    {
        $this->authorize('view', $fiberCable);

        $cable = $this->cables->findWithGeoJson($fiberCable->id);

        return new FiberCableResource($cable->load(['company', 'startSite', 'endSite']));
    }

    public function update(UpdateFiberCableRequest $request, FiberCable $fiberCable)
    {
        $this->authorize('update', $fiberCable);

        $oldSummary = $this->currentGeometrySummary($fiberCable->id);
        $cable = $this->cables->update($fiberCable, $request->validated(), $request->user());

        $metadata = $this->safeAuditMetadata($request->validated());
        if (isset($request->validated()['route_geometry'])) {
            $metadata['route_geometry'] = [
                'old' => $oldSummary,
                'new' => FiberCableService::geometrySummary($request->validated()['route_geometry']),
            ];
        }
        AuditService::log('fim.cable.updated', 'success', $cable, $metadata);

        return new FiberCableResource($cable->load(['company', 'startSite', 'endSite']));
    }

    public function destroy(FiberCable $fiberCable)
    {
        $this->authorize('delete', $fiberCable);

        $fiberCable->delete();

        AuditService::log('fim.cable.deleted', 'success', $fiberCable, [
            'company_id' => $fiberCable->company_id,
            'cable_code' => $fiberCable->cable_code,
        ]);

        return response()->json(['message' => 'Fiber cable deleted successfully.']);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function safeAuditMetadata(array $validated): array
    {
        $allowed = [
            'company_id',
            'cable_code',
            'name',
            'cable_type',
            'fiber_count',
            'status',
            'start_site_id',
            'end_site_id',
            'length_meters',
            'installation_date',
            'survey_source',
            'surveyed_at',
            'surveyed_by',
        ];

        $metadata = array_intersect_key($validated, array_flip($allowed));

        // Geometry is audited as compact evidence only, never full coordinates here.
        if (isset($validated['route_geometry']) && is_array($validated['route_geometry'])) {
            $metadata['route_geometry'] = FiberCableService::geometrySummary($validated['route_geometry']);
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentGeometrySummary(int $id): ?array
    {
        $cable = $this->cables->findWithGeoJson($id);
        $geojson = FiberCableService::decodeRouteGeoJson($cable->getAttribute('route_geojson'));

        return $geojson === null ? null : FiberCableService::geometrySummary($geojson);
    }
}
