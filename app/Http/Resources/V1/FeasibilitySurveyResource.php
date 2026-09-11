<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeasibilitySurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feasibility_check_id' => $this->feasibility_check_id,
            'company_id' => $this->company_id,
            'assigned_to' => $this->assigned_to,
            'assigned_to_name' => $this->assigned_to ? User::query()->whereKey($this->assigned_to)->value('name') : null,
            'status' => $this->status,
            'requested_at' => $this->requested_at,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'location_verified' => $this->location_verified,
            'coordinates_verified_lat' => $this->coordinates_verified_lat !== null ? (float) $this->coordinates_verified_lat : null,
            'coordinates_verified_lng' => $this->coordinates_verified_lng !== null ? (float) $this->coordinates_verified_lng : null,
            'nearest_infrastructure_notes' => $this->nearest_infrastructure_notes,
            'access_path_notes' => $this->access_path_notes,
            'civil_work_required' => $this->civil_work_required,
            'installation_complexity' => $this->installation_complexity,
            'signal_observations' => $this->signal_observations,
            'survey_notes' => $this->survey_notes,
            'findings' => $this->findings,
            'recommended_outcome' => $this->recommended_outcome,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
