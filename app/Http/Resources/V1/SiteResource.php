<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_code' => $this->site_code,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->description,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'altitude' => $this->altitude,
            'province' => $this->province,
            'district' => $this->district,
            'municipality' => $this->municipality,
            'ward' => $this->ward,
            'tole' => $this->tole,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'company_id' => $this->company_id,
            'region_id' => $this->region_id,
            'branch_id' => $this->branch_id,
            'assets_count' => $this->whenCounted('assets'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Eager-loaded relationships
            'company' => new CompanyResource($this->whenLoaded('company')),
            'region' => new RegionResource($this->whenLoaded('region')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
        ];
    }
}
