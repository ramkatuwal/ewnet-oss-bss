<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assets.create');
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'exists:sites,id'],
            'asset_tag' => ['required', 'string', 'max:255', 'unique:assets,asset_tag'],
            'serial_number' => ['nullable', 'string', 'max:255', 'unique:assets,serial_number'],
            'category' => ['required', Rule::in(Asset::CATEGORIES)],
            'type' => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(Asset::STATUSES)],
            'condition' => ['nullable', Rule::in(Asset::CONDITIONS)],
            'purchase_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'warranty_expiry' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'specifications' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();

            // Scope check: Ensure user has access to the site
            $site = Site::find($data['site_id']);
            if ($site && !$this->user()->can('view', $site)) {
                $validator->errors()->add('site_id', 'You do not have permission to add assets to this site.');
            }

            // Conditional serial-number rule:
            // serial_number is required when quantity == 1 and category is POWER or NETWORK
            if (($data['quantity'] ?? 1) == 1 && in_array($data['category'] ?? '', ['POWER', 'NETWORK'])) {
                if (empty($data['serial_number'])) {
                    $validator->errors()->add('serial_number', 'Serial number is required for this category when quantity is 1.');
                }
            }
        });
    }
}
