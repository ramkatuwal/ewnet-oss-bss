<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuctResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'ward_id' => $this->ward_id,
            'duct_code' => $this->duct_code,
            'type' => $this->type,
            'material' => $this->material,
            'diameter' => $this->diameter,
            'status' => $this->status,
            'ownership' => $this->ownership,
            'geometry' => $this->geometry ? json_decode($this->geometry, true) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
