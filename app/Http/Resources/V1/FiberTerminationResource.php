<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiberTerminationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fiber_core_id' => $this->fiber_core_id,
            'company_id' => $this->company_id,
            'network_connection_point_id' => $this->network_connection_point_id,
            'segment_end' => $this->segment_end,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
