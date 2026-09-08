<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiberSegmentCapacityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'fiber_segment_id' => $this->resource['fiber_segment_id'],
            'nominal_capacity' => $this->resource['nominal_capacity'],
            'inventoried_count' => $this->resource['inventoried_count'],
            'unaccounted' => $this->resource['unaccounted'],
            'inventory_complete' => $this->resource['inventory_complete'],
            'data_inconsistency' => $this->resource['data_inconsistency'],
            'termination_count' => $this->resource['termination_count'],
            'fully_terminated_core_count' => $this->resource['fully_terminated_core_count'],
            'partially_terminated_core_count' => $this->resource['partially_terminated_core_count'],
            'unterminated_core_count' => $this->resource['unterminated_core_count'],
            'connected_termination_count' => $this->resource['connected_termination_count'],
            'no_external_connectivity_core_count' => $this->resource['no_external_connectivity_core_count'],
            'partially_connected_core_count' => $this->resource['partially_connected_core_count'],
            'fully_connected_core_count' => $this->resource['fully_connected_core_count'],
        ];
    }
}
