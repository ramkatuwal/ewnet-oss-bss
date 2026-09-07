<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkConnectionPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'point_type' => $this->point_type,
            'name' => $this->name,
            'description' => $this->description,
            'site_id' => $this->site_id,
            'asset_id' => $this->asset_id,
            'asset_interface_id' => $this->asset_interface_id,
            'geometry' => $this->geometry,
            'company_id' => $this->company_id,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'site' => new SiteResource($this->whenLoaded('site')),
            'asset' => new AssetResource($this->whenLoaded('asset')),
        ];
    }
}
