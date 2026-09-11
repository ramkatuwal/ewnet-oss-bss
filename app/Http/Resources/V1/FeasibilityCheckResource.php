<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeasibilityCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'feasibility_code' => $this->feasibility_code,
            'lead_id' => $this->lead_id,
            'customer_id' => $this->customer_id,
            'customer_address_id' => $this->customer_address_id,
            'requested_service_summary' => $this->requested_service_summary,
            'requested_location_summary' => $this->requested_location_summary,
            'requested_location_lat' => $this->requested_location_lat !== null ? (float) $this->requested_location_lat : null,
            'requested_location_lng' => $this->requested_location_lng !== null ? (float) $this->requested_location_lng : null,
            'status' => $this->status,
            'outcome' => $this->outcome,
            'assessment_method' => $this->assessment_method,
            'assigned_assessor_user_id' => $this->assigned_assessor_user_id,
            'requested_at' => $this->requested_at,
            'assessment_started_at' => $this->assessment_started_at,
            'assessed_at' => $this->assessed_at,
            'valid_until' => $this->valid_until,
            'conditions_summary' => $this->conditions_summary,
            'estimated_work_summary' => $this->estimated_work_summary,
            'internal_notes' => $this->internal_notes,
            'customer_safe_summary' => $this->customer_safe_summary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'lead' => $this->when($this->relationLoaded('lead'), fn () => $this->lead ? [
                'id' => $this->lead->id, 'lead_code' => $this->lead->lead_code, 'name' => $this->lead->name, 'status' => $this->lead->status,
            ] : null),
            'customer' => $this->when($this->relationLoaded('customer'), fn () => $this->customer ? [
                'id' => $this->customer->id, 'customer_code' => $this->customer->customer_code, 'name' => $this->customer->name, 'status' => $this->customer->status,
            ] : null),
            'customer_address' => $this->when($this->relationLoaded('customerAddress'), fn () => $this->customerAddress ? [
                'id' => $this->customerAddress->id, 'line1' => $this->customerAddress->line1, 'city' => $this->customerAddress->city,
            ] : null),
            'assigned_assessor' => $this->when($this->relationLoaded('assignedAssessor'), fn () => $this->assignedAssessor ? [
                'id' => $this->assignedAssessor->id, 'name' => $this->assignedAssessor->name,
            ] : null),
            'evidence' => FeasibilityEvidenceResource::collection($this->whenLoaded('evidence')),
            'survey' => FeasibilitySurveyResource::make($this->whenLoaded('survey')),
            'conditions' => FeasibilityConditionResource::collection($this->whenLoaded('conditions')),
            'confirmation' => CustomerConfirmationResource::make($this->whenLoaded('confirmation')),
            'lifecycle_history' => $this->when($this->relationLoaded('lifecycleHistory'), fn () => $this->lifecycleHistory->map(fn ($entry) => [
                'id' => $entry->id, 'from_status' => $entry->from_status, 'to_status' => $entry->to_status,
                'from_outcome' => $entry->from_outcome, 'to_outcome' => $entry->to_outcome, 'context' => $entry->context,
                'actor' => $entry->actor_id ? ($entry->actor?->name ?? null) : null,
                'created_at' => $entry->created_at,
            ])),
        ];
    }
}
