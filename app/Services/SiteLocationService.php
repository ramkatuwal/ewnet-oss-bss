<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteLocationService
{
    public function create(array $attributes): Site
    {
        $geometry = $this->takeGeometry($attributes);
        $this->validateCompatibilityCoordinates($attributes, $geometry);
        $site = Site::create($attributes);

        if ($geometry !== null) {
            $this->setGeometry($site->id, $geometry);
        }

        return $this->findWithGeoJson($site->id);
    }

    public function update(Site $site, array $attributes): Site
    {
        $hasGeometry = array_key_exists('geometry', $attributes);
        $geometry = $this->takeGeometry($attributes);
        $this->validateCompatibilityCoordinates($attributes, $geometry);
        if ($hasGeometry && $geometry === null && ! isset($attributes['latitude'], $attributes['longitude'])) {
            $attributes['latitude'] = null;
            $attributes['longitude'] = null;
        }
        $site->update($attributes);

        if ($geometry !== null) {
            $this->setGeometry($site->id, $geometry);
        }

        return $this->findWithGeoJson($site->id);
    }

    public function findWithGeoJson(int $id): Site
    {
        return Site::query()->select('sites.*')->selectRaw('ST_AsGeoJSON(sites.geometry) AS geometry_geojson')->findOrFail($id);
    }

    private function takeGeometry(array &$attributes): ?array
    {
        $geometry = $attributes['geometry'] ?? null;
        unset($attributes['geometry']);

        return $geometry;
    }

    private function validateCompatibilityCoordinates(array $attributes, ?array $geometry): void
    {
        if ($geometry === null || ! isset($attributes['latitude'], $attributes['longitude'])) {
            return;
        }

        [$longitude, $latitude] = $geometry['coordinates'];
        if ((float) $attributes['latitude'] !== (float) $latitude || (float) $attributes['longitude'] !== (float) $longitude) {
            throw ValidationException::withMessages(['geometry' => 'Geometry and latitude/longitude must describe the same point.']);
        }
    }

    private function setGeometry(int $id, array $geometry): void
    {
        DB::update(
            'UPDATE sites SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), updated_at = ? WHERE id = ?',
            [json_encode($geometry, JSON_THROW_ON_ERROR), now(), $id],
        );
    }
}
