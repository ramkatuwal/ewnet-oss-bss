<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'company_id' => $this->company_id, 'customer_id' => $this->customer_id, 'service_id' => $this->service_id, 'status' => $this->status, 'starts_on' => $this->starts_on?->toDateString(), 'ends_on' => $this->ends_on?->toDateString(), 'activated_at' => $this->activated_at, 'terminated_at' => $this->terminated_at, 'metadata' => $this->metadata, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'customer' => CustomerResource::make($this->whenLoaded('customer')), 'service' => ServiceResource::make($this->whenLoaded('service'))];
    }
}
