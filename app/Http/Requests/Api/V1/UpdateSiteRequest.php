<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sites.update', $this->route('site'));
    }

    public function rules(): array
    {
        $siteId = $this->route('site')->id;
        return [
            'site_code' => ['sometimes', 'string', 'max:255', Rule::unique('sites', 'site_code')->ignore($siteId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Site::TYPES)],
            'status' => ['sometimes', Rule::in(Site::STATUSES)],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'numeric'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'ward' => ['nullable', 'string', 'max:255'],
            'tole' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            
            // Organizational Integrity Checks (same as Store)
            if (!empty($data['region_id']) && !empty($data['company_id'])) {
                $region = Region::find($data['region_id']);
                if ($region && $region->company_id != $data['company_id']) {
                    $validator->errors()->add('region_id', 'The selected region does not belong to the selected company.');
                }
            }

            if (!empty($data['branch_id'])) {
                $branch = Branch::find($data['branch_id']);
                if ($branch) {
                    if (!empty($data['region_id']) && $branch->region_id != $data['region_id']) {
                        $validator->errors()->add('branch_id', 'The selected branch does not belong to the selected region.');
                    }
                    if (!empty($data['company_id']) && $branch->region->company_id != $data['company_id']) {
                        $validator->errors()->add('branch_id', 'The selected branch does not belong to the selected company.');
                    }
                }
            }
        });
    }
}
