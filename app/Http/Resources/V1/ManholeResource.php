<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManholeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'ward_id' => $this->ward_id,
            'site_id' => $this->site_id,
            'manhole_code' => $this->manhole_code,
            'type' => $this->type,
            'status' => $this->status,
            'condition' => $this->condition,
            'depth' => $this->depth,
            'location' => $this->location ? json_decode($this->location, true) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
