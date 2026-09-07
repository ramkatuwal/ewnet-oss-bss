<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiberCoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fiber_segment_id' => $this->fiber_segment_id,
            'company_id' => $this->company_id,
            'core_number' => $this->core_number,
            'status' => $this->status,
            'color_code' => $this->color_code,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
