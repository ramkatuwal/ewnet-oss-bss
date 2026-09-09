<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkPortSwitchingConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'network_port_id' => $this->network_port_id,
            'company_id' => $this->company_id,
            'mode' => $this->mode,
            'metadata' => $this->metadata,
            'memberships' => NetworkPortVlanMembershipResource::collection($this->whenLoaded('memberships')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
