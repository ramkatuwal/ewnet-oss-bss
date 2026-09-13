<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDeviceType;
use App\Models\Site;
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
        $categoryCode = $this->input('category') ?? $asset?->category;

        return [
            'site_id' => ['sometimes', 'exists:sites,id'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'management_ip' => ['nullable', 'ip', Rule::when($categoryCode && $categoryCode !== 'NETWORK', 'prohibited')],
            'asset_tag' => ['sometimes', 'string', 'max:255', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('assets', 'serial_number')->ignore($asset?->id)],
            'category' => ['sometimes', 'string', Rule::exists('asset_categories', 'code')->where('is_active', true)],
            'type' => ['sometimes', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', Rule::exists('asset_units', 'code')->where('is_active', true)],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $asset = $this->route('asset');
            $siteId = $this->input('site_id');
            if ($siteId) {
                $site = Site::find($siteId);
                if ($site && ! $this->user()->can('view', $site)) {
                    $validator->errors()->add('site_id', 'You do not have permission to assign assets to this site.');
                }
            }

            // Validate type belongs to category (case-insensitive match)
            $categoryCode = $this->input('category') ?? $asset?->category;
            $typeCode = $this->input('type') ?? $asset?->type;
            if ($categoryCode && $typeCode) {
                $category = AssetCategory::where('code', $categoryCode)->first();
                if ($category) {
                    $deviceType = AssetDeviceType::where('category_id', $category->id)
                        ->whereRaw('LOWER(code) = LOWER(?)', [$typeCode])
                        ->first();
                    $unchanged = $asset?->category === $categoryCode && strcasecmp((string) $asset?->type, (string) $typeCode) === 0;
                    if ($deviceType === null || (! $deviceType->is_active && ! $unchanged)) {
                        $validator->errors()->add('type', "The selected type is not valid for the {$categoryCode} category.");
                    }
                }
            }
        });
    }
}
