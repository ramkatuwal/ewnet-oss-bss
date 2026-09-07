<?php

namespace App\Services\Fim;

use App\Models\FiberCable;
use App\Models\FiberSegment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FiberSegmentService
{
    public function create(array $validated, User $user): FiberSegment
    {
        return DB::transaction(function () use ($validated, $user) {
            $geometry = $validated['geometry'] ?? null;
            unset($validated['geometry']);
            $cable = FiberCable::whereKey($validated['fiber_cable_id'])->lockForUpdate()->firstOrFail();
            $validated['company_id'] = $cable->company_id;
            $validated['created_by'] = $user->id;
            $validated['updated_by'] = $user->id;

            $segment = FiberSegment::create($validated);
            if ($geometry !== null) {
                $this->writeGeometry($segment->id, $geometry);
            }
            $this->synchronizeCableRouteGeometry($segment->fiber_cable_id);

            return $this->findWithGeoJson($segment->id);
        });
    }

    public function update(FiberSegment $segment, array $validated, User $user): FiberSegment
    {
        return DB::transaction(function () use ($segment, $validated, $user) {
            FiberCable::whereKey($segment->fiber_cable_id)->lockForUpdate()->firstOrFail();
            $geometryProvided = array_key_exists('geometry', $validated);
            $geometry = $validated['geometry'] ?? null;
            unset($validated['geometry']);

            $segment->update($validated + ['updated_by' => $user->id]);
            if ($geometryProvided) {
                $this->writeGeometry($segment->id, $geometry);
            }
            $this->synchronizeCableRouteGeometry($segment->fiber_cable_id);

            return $this->findWithGeoJson($segment->id);
        });
    }

    public function delete(FiberSegment $segment): void
    {
        DB::transaction(function () use ($segment) {
            $cableId = $segment->fiber_cable_id;
            FiberCable::whereKey($cableId)->lockForUpdate()->firstOrFail();
            $segment->delete();
            $this->synchronizeCableRouteGeometry($cableId);
        });
    }

    public function findWithGeoJson(int $id): FiberSegment
    {
        return FiberSegment::query()
            ->select('fiber_segments.*')
            ->selectRaw('ST_AsGeoJSON(fiber_segments.geometry) AS geometry_geojson')
            ->selectRaw('ST_Length(fiber_segments.geometry::geography) AS calculated_length_meters')
            ->whereKey($id)
            ->firstOrFail();
    }

    public static function geometrySummary(?array $geojson): array
    {
        return FiberCableService::geometrySummary($geojson ?? []);
    }

    /**
     * Segments become the sole route authority once one exists. A final segment
     * deletion intentionally clears the parent route rather than restoring an
     * obsolete pre-segmentation route.
     */
    protected function synchronizeCableRouteGeometry(int $cableId): void
    {
        $hasSegments = FiberSegment::where('fiber_cable_id', $cableId)->exists();
        if (! $hasSegments) {
            DB::update(
                "UPDATE fiber_cables SET route_geometry = NULL, route_geometry_authority = 'cable' WHERE id = ?",
                [$cableId]
            );

            return;
        }

        DB::update(
            <<<'SQL'
            UPDATE fiber_cables
            SET route_geometry_authority = 'segments',
                route_geometry = (
                    SELECT CASE
                        WHEN ST_GeometryType(merged.geometry) = 'ST_LineString' THEN merged.geometry
                        ELSE NULL
                    END
                    FROM (
                        SELECT ST_LineMerge(ST_Collect(geometry)) AS geometry
                        FROM fiber_segments
                        WHERE fiber_cable_id = ? AND deleted_at IS NULL AND geometry IS NOT NULL
                    ) AS merged
                )
            WHERE id = ?
            SQL,
            [$cableId, $cableId]
        );
    }

    /** @param array<string, mixed>|null $geometry */
    protected function writeGeometry(int $segmentId, ?array $geometry): void
    {
        if ($geometry === null) {
            DB::update('UPDATE fiber_segments SET geometry = NULL WHERE id = ?', [$segmentId]);

            return;
        }

        DB::update(
            'UPDATE fiber_segments SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?',
            [json_encode($geometry), $segmentId]
        );
    }
}
