<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FimMapRequest;
use App\Models\FiberCable;
use App\Models\NetworkConnectionPoint;
use App\Services\ManagementScopeService;
use Illuminate\Database\Eloquent\Builder;

class FimMapController extends Controller
{
    private const FEATURE_LIMIT = 1000;

    public function index(FimMapRequest $request)
    {
        $user = $request->user();
        $canViewCables = $user->hasPermissionTo('fim.cables.view');
        $canViewPoints = $user->hasPermissionTo('fim.connection-points.view');

        abort_unless($canViewCables || $canViewPoints, 403);

        $layers = $request->validated('layers', ['cables', 'points']);
        $bounds = [
            $request->validated('west'), $request->validated('south'),
            $request->validated('east'), $request->validated('north'),
        ];
        $features = [];

        if (in_array('cables', $layers, true) && $canViewCables) {
            $query = ManagementScopeService::applyScopeToQuery(FiberCable::query(), $user, FiberCable::class)
                ->whereNotNull('route_geometry')
                ->whereRaw('ST_Intersects(route_geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))', $bounds);
            if ($request->filled('cable_status')) {
                $query->where('status', $request->validated('cable_status'));
            }
            $features = [...$features, ...$this->cableFeatures($query)];
        }

        if (in_array('points', $layers, true) && $canViewPoints) {
            $query = ManagementScopeService::applyScopeToQuery(NetworkConnectionPoint::query(), $user, NetworkConnectionPoint::class)
                ->whereNotNull('geometry')
                ->whereRaw('ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))', $bounds);
            if ($request->filled('point_status')) {
                $query->where('status', $request->validated('point_status'));
            }
            $features = [...$features, ...$this->pointFeatures($query)];
        }

        if (count($features) > self::FEATURE_LIMIT) {
            return response()->json([
                'message' => 'The viewport contains too many authorized map features. Zoom in or apply a server filter.',
                'code' => 'fim_map_feature_limit_exceeded',
                'limit' => self::FEATURE_LIMIT,
                'count' => count($features),
            ], 422);
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /** @return array<int, array<string, mixed>> */
    private function cableFeatures(Builder $query): array
    {
        return $query->select(['id', 'cable_code', 'name', 'status'])
            ->selectRaw('ST_AsGeoJSON(route_geometry)::json AS geometry')
            ->limit(self::FEATURE_LIMIT + 1)
            ->get()
            ->map(fn (FiberCable $cable) => [
                'type' => 'Feature',
                'id' => 'cable:'.$cable->id,
                'geometry' => json_decode($cable->getAttribute('geometry'), true, flags: JSON_THROW_ON_ERROR),
                'properties' => ['kind' => 'cable', 'id' => $cable->id, 'label' => $cable->cable_code ?: $cable->name, 'status' => $cable->status],
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function pointFeatures(Builder $query): array
    {
        return $query->select(['id', 'name', 'point_type', 'status'])
            ->selectRaw('ST_AsGeoJSON(geometry)::json AS geometry')
            ->limit(self::FEATURE_LIMIT + 1)
            ->get()
            ->map(fn (NetworkConnectionPoint $point) => [
                'type' => 'Feature',
                'id' => 'point:'.$point->id,
                'geometry' => json_decode($point->getAttribute('geometry'), true, flags: JSON_THROW_ON_ERROR),
                'properties' => ['kind' => 'point', 'id' => $point->id, 'label' => $point->name ?: $point->point_type, 'status' => $point->status],
            ])->all();
    }
}
