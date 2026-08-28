<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');
        if ($role->name === 'Super Admin' && !$this->user()->hasRole('Super Admin')) {
            return false;
        }
        return $this->user()->hasPermissionTo('roles.update');
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;
        return [
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $roleId,
            'permissions' => 'sometimes|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ];
    }
}
