<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetVlanMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $config = $this->resource->switchingConfig;
        $port = $config?->networkPort;

        return [
            'id' => $this->id,
            'network_port_id' => $port?->id,
            'port_key' => $port?->port_key,
            'port_name' => $port?->name,
            'port_technology' => $port?->technology,
            'mode' => $config?->mode,
            'tagging' => $this->tagging,
            'vlan_id' => $this->vlan_id,
            'vlan' => new VlanResource($this->whenLoaded('vlan')),
            'updated_at' => $this->updated_at,
        ];
    }
}
