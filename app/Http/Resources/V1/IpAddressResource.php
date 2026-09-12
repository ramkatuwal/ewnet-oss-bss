<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IpAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_interface_id' => $this->asset_interface_id,
            'ip_address' => $this->ip_address,
            'family' => $this->family,
            'prefix_length' => $this->prefix_length,
            'is_primary' => $this->is_primary,
            'is_management' => $this->is_management,
            'provider' => $this->provider,
            'external_type' => $this->external_type,
            'external_id' => $this->external_id,
            'first_seen_at' => $this->first_seen_at,
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'interface' => new AssetInterfaceResource($this->whenLoaded('interface')),
        ];
    }
}
