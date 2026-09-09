<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkPortFiberTerminationAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->only([
            'id', 'network_port_id', 'fiber_termination_id', 'company_id', 'metadata',
            'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
        ]);
    }
}
