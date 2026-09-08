<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiberTerminationPortAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fiber_termination_id' => $this->fiber_termination_id,
            'passive_optical_port_id' => $this->passive_optical_port_id,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
