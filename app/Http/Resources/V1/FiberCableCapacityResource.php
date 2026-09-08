<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiberCableCapacityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fiber_cable_id' => $this->resource['fiber_cable_id'],
            'fiber_count' => $this->resource['fiber_count'],
            'segment_count' => $this->resource['segment_count'],
            'segments' => FiberSegmentCapacityResource::collection($this->resource['segments']),
            'fully_inventoried_segment_count' => $this->resource['fully_inventoried_segment_count'],
            'partially_inventoried_segment_count' => $this->resource['partially_inventoried_segment_count'],
            'inventory_complete_across_all_segments' => $this->resource['inventory_complete_across_all_segments'],
        ];
    }
}
