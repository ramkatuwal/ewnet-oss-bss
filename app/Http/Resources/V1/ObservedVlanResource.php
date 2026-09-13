<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObservedVlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'asset_interface_id' => $this->asset_interface_id,
            'vid' => $this->vid,
            'name' => $this->name,
            'vlan_type' => $this->vlan_type,
            'provider' => $this->provider,
            'external_type' => $this->external_type,
            'external_id' => $this->external_id,
            'observation_status' => $this->observation_status,
            'metadata' => $this->metadata,
            'reconciled_vlan_id' => $this->reconciled_vlan_id,
            'first_seen_at' => $this->first_seen_at
                ? $this->first_seen_at->toISOString()
                : null,
            'last_seen_at' => $this->last_seen_at
                ? $this->last_seen_at->toISOString()
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'reconciled_vlan' => new VlanResource($this->whenLoaded('reconciledVlan')),
        ];
    }
}
