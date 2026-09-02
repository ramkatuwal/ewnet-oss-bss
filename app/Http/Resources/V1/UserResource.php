<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'avatar' => $this->avatar,
            'is_active' => $this->is_active,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'company' => $this->whenLoaded('company', fn() => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn() => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'region' => $this->branch->relationLoaded('region') ? [
                    'id' => $this->branch->region->id,
                    'name' => $this->branch->region->name,
                ] : null,
            ]),
            'department' => $this->whenLoaded('department', fn() => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ])),
            'management_scopes' => $this->whenLoaded('managementScopes', fn() => $this->managementScopes->map(fn($s) => [
                'id' => $s->id,
                'scope_type' => $s->scope_type,
                'scope_id' => $s->scope_id,
                'scope_name' => $s->scope_name,
                'granted_by' => $s->grantor?->name,
                'granted_at' => $s->granted_at?->toIso8601String(),
            ])),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
