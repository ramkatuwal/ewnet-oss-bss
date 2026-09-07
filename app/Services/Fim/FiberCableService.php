<?php

namespace App\Services\Fim;

use App\Models\FiberCable;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FiberCableService
{
    /**
     * Create a cable with authoritative PostGIS route geometry.
     *
     * Single raw INSERT with full parameter binding: geometry is passed as a
     * bound GeoJSON string into ST_GeomFromGeoJSON, never concatenated.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $user): FiberCable
    {
        $geojson = $validated['route_geometry'];
        unset($validated['route_geometry']);

        $now = now();
        $row = DB::selectOne(
            <<<'SQL'
            INSERT INTO fiber_cables
                (company_id, cable_code, name, cable_type, fiber_count, status,
                 start_site_id, end_site_id, length_meters, installation_date,
                 survey_source, surveyed_at, surveyed_by, metadata,
                 created_by, updated_by, created_at, updated_at, route_geometry)
            VALUES
                (?, ?, ?, ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, ?, ?, ?::jsonb,
                 ?, ?, ?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))
            RETURNING id
            SQL,
            [
                $validated['company_id'],
                $validated['cable_code'],
                $validated['name'],
                $validated['cable_type'],
                $validated['fiber_count'],
                $validated['status'],
                $validated['start_site_id'] ?? null,
                $validated['end_site_id'] ?? null,
                $validated['length_meters'] ?? null,
                $validated['installation_date'] ?? null,
                $validated['survey_source'] ?? null,
                $validated['surveyed_at'] ?? null,
                $validated['surveyed_by'] ?? null,
                isset($validated['metadata']) ? json_encode($validated['metadata']) : null,
                $user->id,
                $user->id,
                $now,
                $now,
                json_encode($geojson),
            ]
        );

        return $this->findWithGeoJson((int) $row->id);
    }

    /**
     * Update a cable and optionally its authoritative route geometry.
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(FiberCable $cable, array $validated, User $user): FiberCable
    {
        $geojson = $validated['route_geometry'] ?? null;
        unset($validated['route_geometry']);

        DB::transaction(function () use ($cable, $validated, $geojson, $user) {
            $sets = [];
            $bindings = [];
            foreach (['cable_code', 'name', 'cable_type', 'fiber_count', 'status',
                'start_site_id', 'end_site_id', 'length_meters', 'installation_date',
                'survey_source', 'surveyed_at', 'surveyed_by', 'metadata'] as $field) {
                if (array_key_exists($field, $validated)) {
                    if ($field === 'metadata') {
                        $sets[] = 'metadata = ?::jsonb';
                        $bindings[] = isset($validated['metadata']) ? json_encode($validated['metadata']) : null;
                    } else {
                        $sets[] = "{$field} = ?";
                        $bindings[] = $validated[$field];
                    }
                }
            }
            // company_id is immutable in FIM-002; never updated here.
            $sets[] = 'updated_by = ?';
            $bindings[] = $user->id;
            $sets[] = 'updated_at = ?';
            $bindings[] = now();

            if ($geojson !== null) {
                $sets[] = 'route_geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)';
                $bindings[] = json_encode($geojson);
            }

            $bindings[] = $cable->id;
            DB::update('UPDATE fiber_cables SET '.implode(', ', $sets).' WHERE id = ?', $bindings);
        });

        return $this->findWithGeoJson($cable->id);
    }

    public function findWithGeoJson(int $id): FiberCable
    {
        return FiberCable::query()
            ->select('fiber_cables.*')
            ->selectRaw('ST_AsGeoJSON(fiber_cables.route_geometry) AS route_geojson')
            ->where('fiber_cables.id', $id)
            ->firstOrFail();
    }

    /**
     * Compact geometry evidence for audit logs (no full coordinate dump).
     *
     * @param  array<string, mixed>  $geojson
     * @return array<string, mixed>
     */
    public static function geometrySummary(array $geojson): array
    {
        $coords = $geojson['coordinates'] ?? [];
        $lngs = [];
        $lats = [];
        foreach ($coords as $pos) {
            if (is_array($pos) && count($pos) === 2 && is_numeric($pos[0]) && is_numeric($pos[1])) {
                $lngs[] = (float) $pos[0];
                $lats[] = (float) $pos[1];
            }
        }

        return [
            'route_hash' => hash('sha256', json_encode($geojson)),
            'point_count' => count($coords),
            'bbox' => $lngs === [] ? null : [
                min($lngs),
                min($lats),
                max($lngs),
                max($lats),
            ],
        ];
    }

    /**
     * Decode a ST_AsGeoJSON string into a GeoJSON-compatible array.
     *
     * @return array<string, mixed>|null
     */
    public static function decodeRouteGeoJson(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
