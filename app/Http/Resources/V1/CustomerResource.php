<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'company_id' => $this->company_id, 'customer_code' => $this->customer_code, 'name' => $this->name, 'type' => $this->type, 'status' => $this->status, 'email' => $this->email, 'phone' => $this->phone, 'address' => $this->address, 'metadata' => $this->metadata, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at, 'deleted_at' => $this->deleted_at, 'company' => CompanyResource::make($this->whenLoaded('company'))];
    }
}
