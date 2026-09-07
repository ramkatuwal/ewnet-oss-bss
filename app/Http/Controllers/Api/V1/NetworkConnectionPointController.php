<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNetworkConnectionPointRequest;
use App\Http\Requests\Api\V1\UpdateNetworkConnectionPointRequest;
use App\Http\Resources\V1\NetworkConnectionPointResource;
use App\Models\NetworkConnectionPoint;
use App\Services\AuditService;
use App\Services\Fim\NetworkConnectionPointService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class NetworkConnectionPointController extends Controller
{
    public function __construct(protected NetworkConnectionPointService $points) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', NetworkConnectionPoint::class);

        $query = NetworkConnectionPoint::query()
            ->select('network_connection_points.*')
            ->selectRaw('ST_AsGeoJSON(network_connection_points.geometry) AS geometry_geojson')
            ->with(['site', 'asset', 'assetInterface']);
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), NetworkConnectionPoint::class);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('point_type', 'ilike', "%{$search}%");
            });
        }
        foreach (['point_type', 'site_id', 'asset_id', 'company_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $query->orderBy('created_at', 'desc');
        $points = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => NetworkConnectionPointResource::collection($points->items()),
            'meta' => [
                'current_page' => $points->currentPage(),
                'last_page' => $points->lastPage(),
                'per_page' => $points->perPage(),
                'total' => $points->total(),
            ],
        ]);
    }

    public function store(StoreNetworkConnectionPointRequest $request)
    {
        $this->authorize('create', NetworkConnectionPoint::class);

        $point = $this->points->create($request->validated(), $request->user());

        AuditService::log('fim.connection-point.created', 'success', $point, $this->safeAuditMetadata($request->validated()));

        return (new NetworkConnectionPointResource($point))->response()->setStatusCode(201);
    }

    public function show(NetworkConnectionPoint $networkConnectionPoint)
    {
        $this->authorize('view', $networkConnectionPoint);

        $networkConnectionPoint = $this->points->findWithGeoJson($networkConnectionPoint->id);

        return new NetworkConnectionPointResource($networkConnectionPoint->load(['site', 'asset', 'assetInterface']));
    }

    public function update(UpdateNetworkConnectionPointRequest $request, NetworkConnectionPoint $networkConnectionPoint)
    {
        $this->authorize('update', $networkConnectionPoint);

        $oldSummary = $this->currentGeometrySummary($networkConnectionPoint->id);
        $networkConnectionPoint = $this->points->update($networkConnectionPoint, $request->validated(), $request->user());

        $metadata = $this->safeAuditMetadata($request->validated());
        if (isset($request->validated()['geometry'])) {
            $metadata['geometry'] = [
                'old' => $oldSummary,
                'new' => NetworkConnectionPointService::geometrySummary($request->validated()['geometry']),
            ];
        }
        AuditService::log('fim.connection-point.updated', 'success', $networkConnectionPoint, $metadata);

        return new NetworkConnectionPointResource($networkConnectionPoint->load(['site', 'asset', 'assetInterface']));
    }

    public function destroy(NetworkConnectionPoint $networkConnectionPoint)
    {
        $this->authorize('delete', $networkConnectionPoint);

        $networkConnectionPoint->delete();

        AuditService::log('fim.connection-point.deleted', 'success', $networkConnectionPoint, [
            'company_id' => $networkConnectionPoint->company_id,
            'point_type' => $networkConnectionPoint->point_type,
        ]);

        return response()->json(['message' => 'Network connection point deleted successfully.']);
    }

    protected function safeAuditMetadata(array $validated): array
    {
        $allowed = [
            'point_type',
            'name',
            'description',
            'site_id',
            'asset_id',
            'asset_interface_id',
            'company_id',
            'status',
            'metadata',
        ];

        $metadata = array_intersect_key($validated, array_flip($allowed));

        if (isset($validated['geometry']) && is_array($validated['geometry'])) {
            $metadata['geometry'] = NetworkConnectionPointService::geometrySummary($validated['geometry']);
        }

        return $metadata;
    }

    protected function currentGeometrySummary(int $id): ?array
    {
        $point = $this->points->findWithGeoJson($id);
        $geojson = json_decode($point->getAttribute('geometry_geojson'), true);

        return $geojson === null ? null : NetworkConnectionPointService::geometrySummary($geojson);
    }
}
