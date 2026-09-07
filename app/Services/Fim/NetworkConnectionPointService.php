<?php

namespace App\Services\Fim;

use App\Models\NetworkConnectionPoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NetworkConnectionPointService
{
    public function create(array $validated, User $user): NetworkConnectionPoint
    {
        $geojson = $validated['geometry'] ?? null;
        unset($validated['geometry']);

        $attributes = $validated + ['created_by' => $user->id, 'updated_by' => $user->id];

        if ($geojson !== null) {
            $attributes['geometry'] = DB::raw('ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)');
        }

        $point = NetworkConnectionPoint::create($attributes);

        if ($geojson !== null) {
            DB::update(
                'UPDATE network_connection_points SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?',
                [json_encode($geojson), $point->id]
            );
        }

        return $point->fresh()->load(['site', 'asset', 'assetInterface']);
    }

    public function update(NetworkConnectionPoint $point, array $validated, User $user): NetworkConnectionPoint
    {
        $geojson = $validated['geometry'] ?? null;
        unset($validated['geometry']);

        $validated['updated_by'] = $user->id;

        if ($geojson !== null) {
            DB::update(
                'UPDATE network_connection_points SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), updated_by = ?, updated_at = ? WHERE id = ?',
                [json_encode($geojson), $user->id, now(), $point->id]
            );
            $point->refresh();
        } else {
            $point->update($validated);
        }

        return $point->fresh()->load(['site', 'asset', 'assetInterface']);
    }

    public function findWithGeoJson(int $id): NetworkConnectionPoint
    {
        return NetworkConnectionPoint::query()
            ->select('network_connection_points.*')
            ->selectRaw('ST_AsGeoJSON(network_connection_points.geometry) AS geometry_geojson')
            ->where('network_connection_points.id', $id)
            ->firstOrFail();
    }

    public static function geometrySummary(?array $geojson): array
    {
        $coords = $geojson['coordinates'] ?? [];

        if (empty($coords)) {
            return [
                'point_hash' => hash('sha256', json_encode($geojson)),
                'coordinate_count' => 0,
                'bbox' => null,
            ];
        }

        if (isset($coords[0]) && is_array($coords[0])) {
            $lngs = array_column($coords, 0);
            $lats = array_column($coords, 1);
        } else {
            $lngs = [$coords[0] ?? 0];
            $lats = [$coords[1] ?? 0];
        }

        $isPoint = ! isset($coords[0]) || ! is_array($coords[0]);
        $pointCount = $isPoint ? 1 : count($coords);

        return [
            'point_hash' => hash('sha256', json_encode($geojson)),
            'coordinate_count' => $pointCount,
            'bbox' => [
                min($lngs),
                min($lats),
                max($lngs),
                max($lats),
            ],
        ];
    }
}
