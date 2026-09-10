<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutingL3InterfaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'routing_instance_id' => $this->routing_instance_id, 'asset_id' => $this->asset_id,
            'company_id' => $this->company_id, 'name' => $this->name, 'kind' => $this->kind,
            'network_port_id' => $this->network_port_id, 'vlan_id' => $this->vlan_id,
            'parent_routing_l3_interface_id' => $this->parent_routing_l3_interface_id, 'metadata' => $this->metadata,
            'created_by' => $this->created_by, 'updated_by' => $this->updated_by, 'created_at' => $this->created_at,
            'updated_at' => $this->updated_at, 'deleted_at' => $this->deleted_at];
    }
}
