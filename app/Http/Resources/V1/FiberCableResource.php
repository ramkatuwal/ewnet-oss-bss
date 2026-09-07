<?php

namespace App\Http\Resources\V1;

use App\Services\Fim\FiberCableService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class FiberCableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $geojson = null;
        $raw = $this->route_geojson ?? null;
        if (is_string($raw)) {
            $geojson = FiberCableService::decodeRouteGeoJson($raw);
        }
        if ($geojson === null) {
            $fetched = DB::selectOne(
                'SELECT ST_AsGeoJSON(route_geometry) AS geojson FROM fiber_cables WHERE id = ?',
                [$this->id]
            );
            $geojson = FiberCableService::decodeRouteGeoJson($fetched->geojson ?? null);
        }

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'cable_code' => $this->cable_code,
            'name' => $this->name,
            'cable_type' => $this->cable_type,
            'fiber_count' => $this->fiber_count,
            'status' => $this->status,
            'start_site_id' => $this->start_site_id,
            'end_site_id' => $this->end_site_id,
            'route_geometry' => $geojson,
            'length_meters' => $this->length_meters,
            'installation_date' => $this->installation_date?->toDateString(),
            'survey_source' => $this->survey_source,
            'surveyed_at' => $this->surveyed_at,
            'surveyed_by' => $this->surveyed_by,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company' => new CompanyResource($this->whenLoaded('company')),
            'start_site' => new SiteResource($this->whenLoaded('startSite')),
            'end_site' => new SiteResource($this->whenLoaded('endSite')),
        ];
    }
}
