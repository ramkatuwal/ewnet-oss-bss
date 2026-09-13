<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetDeviceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('system.config.manage') || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', Rule::exists('asset_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
