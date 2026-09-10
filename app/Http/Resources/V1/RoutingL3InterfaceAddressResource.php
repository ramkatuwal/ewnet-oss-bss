<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutingL3InterfaceAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'routing_l3_interface_id' => $this->routing_l3_interface_id, 'routing_instance_id' => $this->routing_instance_id,
            'asset_id' => $this->asset_id, 'company_id' => $this->company_id, 'address' => $this->address, 'prefix_length' => $this->prefix_length, 'address_role' => $this->address_role,
            'metadata' => $this->metadata, 'created_by' => $this->created_by, 'updated_by' => $this->updated_by,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'deleted_at' => $this->deleted_at];
    }
}
