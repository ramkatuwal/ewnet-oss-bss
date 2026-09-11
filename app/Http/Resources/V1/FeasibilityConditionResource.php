<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeasibilityConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feasibility_check_id' => $this->feasibility_check_id,
            'company_id' => $this->company_id,
            'condition_type' => $this->condition_type,
            'description' => $this->description,
            'is_mandatory' => $this->is_mandatory,
            'status' => $this->status,
            'resolution_notes' => $this->resolution_notes,
            'resolved_by' => $this->resolved_by,
            'resolved_by_name' => $this->resolved_by ? User::query()->whereKey($this->resolved_by)->value('name') : null,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
