<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetInterfaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'type' => $this->type,
            'mac_address' => $this->mac_address,
            'speed' => $this->speed,
            'status' => $this->status,
            'is_management' => $this->is_management,
            'observation_status' => $this->observation_status,
            'provider' => $this->provider,
            'integration_id' => $this->integration_id,
            'external_type' => $this->external_type,
            'external_id' => $this->external_id,
            'metadata' => $this->metadata,
            'reconciled_network_port_id' => $this->reconciled_network_port_id,
            'reconciled_port' => new NetworkPortResource($this->whenLoaded('reconciledNetworkPort')),
            'first_seen_at' => $this->first_seen_at,
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ip_addresses' => IpAddressResource::collection($this->whenLoaded('ipAddresses')),
        ];
    }
}
