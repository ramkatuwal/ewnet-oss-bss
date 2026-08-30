<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetLifecycleEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'event_type' => $this->event_type,
            'status_before' => $this->status_before,
            'status_after' => $this->status_after,
            'from_site_id' => $this->from_site_id,
            'to_site_id' => $this->to_site_id,
            'from_site' => new SiteResource($this->whenLoaded('fromSite')),
            'to_site' => new SiteResource($this->whenLoaded('toSite')),
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'created_by_user' => new UserResource($this->whenLoaded('createdBy')),
            'event_date' => $this->event_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
