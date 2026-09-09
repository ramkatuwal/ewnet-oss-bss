<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkPortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'company_id' => $this->company_id,
            'port_key' => $this->port_key,
            'name' => $this->name,
            'slot' => $this->slot,
            'card' => $this->card,
            'port_number' => $this->port_number,
            'connector_type' => $this->connector_type,
            'port_direction' => $this->port_direction,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
