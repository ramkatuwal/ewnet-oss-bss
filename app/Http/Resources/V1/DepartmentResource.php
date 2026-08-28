<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'is_active' => $this->is_active,
            'user_count' => $this->whenCounted('users'),
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'region' => $this->branch->relationLoaded('region') ? [
                    'id' => $this->branch->region->id,
                    'name' => $this->branch->region->name,
                    'company' => $this->branch->region->relationLoaded('company') ? [
                        'id' => $this->branch->region->company->id,
                        'name' => $this->branch->region->company->name,
                    ] : null,
                ] : null,
            ]),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
