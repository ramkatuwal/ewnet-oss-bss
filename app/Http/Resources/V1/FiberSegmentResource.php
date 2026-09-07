<?php

namespace App\Http\Resources\V1;

use App\Services\Fim\FiberCableService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class FiberSegmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $raw = $this->geometry_geojson;
        if ($raw === null) {
            $raw = DB::table('fiber_segments')->where('id', $this->id)->selectRaw('ST_AsGeoJSON(geometry) AS geometry_geojson')->value('geometry_geojson');
        }

        return [
            'id' => $this->id,
            'fiber_cable_id' => $this->fiber_cable_id,
            'endpoint_a_id' => $this->endpoint_a_id,
            'endpoint_b_id' => $this->endpoint_b_id,
            'company_id' => $this->company_id,
            'sequence' => $this->sequence,
            'geometry' => FiberCableService::decodeRouteGeoJson($raw),
            'length_meters' => $this->length_meters,
            'calculated_length_meters' => $this->calculated_length_meters === null ? null : (float) $this->calculated_length_meters,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'fiber_cable' => new FiberCableResource($this->whenLoaded('fiberCable')),
            'endpoint_a' => new NetworkConnectionPointResource($this->whenLoaded('endpointA')),
            'endpoint_b' => new NetworkConnectionPointResource($this->whenLoaded('endpointB')),
        ];
    }
}
