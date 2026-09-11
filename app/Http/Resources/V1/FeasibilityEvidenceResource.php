<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeasibilityEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feasibility_check_id' => $this->feasibility_check_id,
            'company_id' => $this->company_id,
            'evidence_type' => $this->evidence_type,
            'referenced_entity_type' => $this->referenced_entity_type,
            'referenced_entity_id' => $this->referenced_entity_id,
            'observation_summary' => $this->observation_summary,
            'recorded_by' => $this->recorded_by,
            'recorded_by_name' => $this->recorded_by ? User::query()->whereKey($this->recorded_by)->value('name') : null,
            'recorded_at' => $this->recorded_at,
            'created_at' => $this->created_at,
        ];
    }
}
