<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Site;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNetworkConnectionPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fim.connection-points.create');
    }

    public function rules(): array
    {
        return [
            'point_type' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'asset_interface_id' => ['nullable', 'integer', 'exists:asset_interfaces,id'],
            'geometry' => ['nullable', 'array'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'planned'])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            $user = $this->user();

            $hasSite = isset($data['site_id']);
            $hasAsset = isset($data['asset_id']);
            $hasGeometry = isset($data['geometry']);

            if (! $hasSite && ! $hasAsset && ! $hasGeometry) {
                $validator->errors()->add('base', 'At least one of site_id, asset_id, or geometry is required.');
            }

            if (isset($data['company_id'])) {
                $company = Company::find($data['company_id']);
                if ($company && ! ManagementScopeService::isInScope($user, $company)) {
                    $validator->errors()->add('company_id', 'You do not have permission to create connection points for this company.');
                }
            }

            if (! empty($data['site_id']) && isset($data['company_id'])) {
                $site = Site::find($data['site_id']);
                if ($site && (int) $site->company_id !== (int) $data['company_id']) {
                    $validator->errors()->add('site_id', 'The site must belong to the same company.');
                }
            }

            if (! empty($data['asset_id']) && isset($data['company_id'])) {
                $asset = Asset::find($data['asset_id']);
                if ($asset && $asset->site) {
                    $site = Site::find($asset->site_id);
                    if ($site && (int) $site->company_id !== (int) $data['company_id']) {
                        $validator->errors()->add('asset_id', 'The asset site must belong to the same company.');
                    }
                }
            }
        });
    }
}
