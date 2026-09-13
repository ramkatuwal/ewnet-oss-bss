<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetDeviceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('system.config.manage') || $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $type = $this->route('deviceType');
        $categoryId = $this->input('category_id', $type?->category_id);

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('asset_device_types', 'code')->where(fn ($query) => $query->where('category_id', $categoryId))->ignore($type?->id)],
            'name' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', Rule::exists('asset_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
