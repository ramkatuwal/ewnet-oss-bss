<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'is_protected' => $this->name === 'Super Admin',
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permission_count' => $this->whenLoaded('permissions', fn () => $this->permissions->count()),
            'user_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
