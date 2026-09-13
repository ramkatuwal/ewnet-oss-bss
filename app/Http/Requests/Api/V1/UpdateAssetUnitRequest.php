<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('system.config.manage') || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:50', 'unique:asset_units,code,'.$this->route('unit')?->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
