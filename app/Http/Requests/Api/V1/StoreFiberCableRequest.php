<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Company;
use App\Models\FiberCable;
use App\Models\Site;
use App\Rules\GeoJsonLineString;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiberCableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fim.cables.create');
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'cable_code' => [
                'required', 'string', 'max:100',
                Rule::unique('fiber_cables', 'cable_code')->where('company_id', $this->input('company_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cable_type' => ['required', Rule::in(FiberCable::CABLE_TYPES)],
            'fiber_count' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::in(FiberCable::STATUSES)],
            'start_site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'end_site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'route_geometry' => ['required', 'array', new GeoJsonLineString],
            'length_meters' => ['nullable', 'numeric', 'min:0'],
            'installation_date' => ['nullable', 'date'],
            'survey_source' => ['nullable', 'string', 'max:100'],
            'surveyed_at' => ['nullable', 'date'],
            'surveyed_by' => ['nullable', 'integer', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            $user = $this->user();

            if (! empty($data['company_id'])) {
                $company = Company::find($data['company_id']);
                if ($company && ! ManagementScopeService::isInScope($user, $company)) {
                    $validator->errors()->add('company_id', 'You do not have permission to create cables for this company.');
                }
            }

            foreach (['start_site_id', 'end_site_id'] as $field) {
                if (! empty($data[$field]) && ! empty($data['company_id'])) {
                    $site = Site::find($data[$field]);
                    if ($site && (int) $site->company_id !== (int) $data['company_id']) {
                        $validator->errors()->add($field, 'Endpoint sites must belong to the same company as the cable.');
                    }
                }
            }
        });
    }
}
