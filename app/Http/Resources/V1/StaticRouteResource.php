<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaticRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'routing_instance_id' => $this->routing_instance_id, 'routing_l3_interface_id' => $this->routing_l3_interface_id,
            'asset_id' => $this->asset_id, 'company_id' => $this->company_id, 'destination' => $this->destination, 'gateway' => $this->gateway,
            'route_type' => $this->route_type, 'metadata' => $this->metadata, 'created_by' => $this->created_by, 'updated_by' => $this->updated_by,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'deleted_at' => $this->deleted_at];
    }
}
