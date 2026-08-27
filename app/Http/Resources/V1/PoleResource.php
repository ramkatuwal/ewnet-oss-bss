<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'ward_id' => $this->ward_id,
            'site_id' => $this->site_id,
            'pole_code' => $this->pole_code,
            'pole_number' => $this->pole_number,
            'type' => $this->type,
            'material' => $this->material,
            'height' => $this->height,
            'status' => $this->status,
            'installation_date' => $this->installation_date?->toISOString(),
            'ownership' => $this->ownership,
            'location' => $this->location ? json_decode($this->location, true) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
