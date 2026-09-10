<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'company_id' => $this->company_id, 'service_code' => $this->service_code, 'name' => $this->name, 'type' => $this->type, 'status' => $this->status, 'description' => $this->description, 'metadata' => $this->metadata, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'deleted_at' => $this->deleted_at, 'company' => CompanyResource::make($this->whenLoaded('company'))];
    }
}
