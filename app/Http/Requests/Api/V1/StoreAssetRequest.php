<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDeviceType;
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
        $categoryCode = $this->input('category');

        return [
            'site_id' => ['required', 'exists:sites,id'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'management_ip' => ['nullable', 'ip', Rule::when($categoryCode && $categoryCode !== 'NETWORK', 'prohibited')],
            'serial_number' => ['nullable', 'string', 'max:255', 'unique:assets,serial_number'],
            'category' => ['required', 'string', Rule::exists('asset_categories', 'code')->where('is_active', true)],
            'type' => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', Rule::exists('asset_units', 'code')->where('is_active', true)],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $siteId = $this->input('site_id');
            if ($siteId) {
                $site = Site::find($siteId);
                if ($site && ! $this->user()->can('view', $site)) {
                    $validator->errors()->add('site_id', 'You do not have permission to add assets to this site.');
                }
            }

            // serial_number is required when quantity == 1 and category is POWER or NETWORK
            $quantity = (int) ($this->input('quantity') ?? 1);
            $category = $this->input('category') ?? '';
            if ($quantity === 1 && in_array($category, ['POWER', 'NETWORK'], true)) {
                if (empty($this->input('serial_number'))) {
                    $validator->errors()->add('serial_number', 'Serial number is required for this category when quantity is 1.');
                }
            }

            // Validate type belongs to category (case-insensitive match)
            $categoryCode = $this->input('category');
            $typeCode = $this->input('type');
            if ($categoryCode && $typeCode) {
                $cat = AssetCategory::where('code', $categoryCode)->first();
                if ($cat) {
                    $typeValid = AssetDeviceType::where('category_id', $cat->id)
                        ->whereRaw('LOWER(code) = LOWER(?)', [$typeCode])
                        ->where('is_active', true)
                        ->exists();
                    if (! $typeValid) {
                        $validator->errors()->add('type', "The selected type is not valid for the {$categoryCode} category.");
                    }
                }
            }
        });
    }
}
