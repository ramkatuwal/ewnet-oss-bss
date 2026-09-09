<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkPortVlanMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vlan_id' => $this->vlan_id,
            'tagging' => $this->tagging,
            'metadata' => $this->metadata,
        ];
    }
}
