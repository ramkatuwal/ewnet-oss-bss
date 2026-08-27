<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn() => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'logo_url' => $this->company->logo_path
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->company->logo_path)
                    : null,
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'settings' => $this->settings,
            'is_active' => $this->is_active,
            'branches_count' => $this->whenCounted('branches'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
