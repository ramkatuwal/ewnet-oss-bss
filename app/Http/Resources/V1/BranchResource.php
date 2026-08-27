<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'region_id' => $this->region_id,
            'region' => $this->whenLoaded('region', fn() => [
                'id' => $this->region->id,
                'name' => $this->region->name,
                'code' => $this->region->code,
                'company_id' => $this->region->company_id,
                'company' => $this->region->relationLoaded('company') ? [
                    'id' => $this->region->company->id,
                    'name' => $this->region->company->name,
                ] : null,
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'settings' => $this->settings,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
