<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'asset_tag' => $this->asset_tag,
            'serial_number' => $this->serial_number,
            'category' => $this->category,
            'type' => $this->type,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'status' => $this->status,
            'condition' => $this->condition,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'installation_date' => $this->installation_date?->toDateString(),
            'warranty_expiry' => $this->warranty_expiry?->toDateString(),
            'specifications' => $this->specifications,
            'description' => $this->description,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Eager-loaded relationships
            'site' => new SiteResource($this->whenLoaded('site')),
        ];
    }
}
