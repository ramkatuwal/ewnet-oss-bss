<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'domain' => str_contains($this->name, '.') ? explode('.', $this->name)[0] : 'other',
            'action' => str_contains($this->name, '.') ? explode('.', $this->name, 2)[1] : $this->name,
            'role_count' => $this->whenCounted('roles'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
