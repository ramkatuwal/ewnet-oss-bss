<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('asset'));
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        return [
            'site_id' => ['sometimes', 'exists:sites,id'],
            'asset_tag' => ['sometimes', 'string', 'max:255', Rule::unique('assets', 'asset_tag')->ignore($asset->id)],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets', 'serial_number')->ignore($asset->id)],
            'category' => ['sometimes', Rule::in(Asset::CATEGORIES)],
            'type' => ['sometimes', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(Asset::STATUSES)],
            'condition' => ['nullable', Rule::in(Asset::CONDITIONS)],
            'purchase_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'warranty_expiry' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'specifications' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
