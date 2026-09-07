<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberCable;
use App\Models\Site;
use App\Rules\GeoJsonLineString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFiberCableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fiberCable'));
    }

    public function rules(): array
    {
        /** @var FiberCable $cable */
        $cable = $this->route('fiberCable');
        $companyId = $this->input('company_id', $cable->company_id);

        return [
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'cable_code' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('fiber_cables', 'cable_code')->ignore($cable->id)->where('company_id', $companyId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'cable_type' => ['sometimes', Rule::in(FiberCable::CABLE_TYPES)],
            'fiber_count' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(FiberCable::STATUSES)],
            'start_site_id' => ['sometimes', 'nullable', 'integer', 'exists:sites,id'],
            'end_site_id' => ['sometimes', 'nullable', 'integer', 'exists:sites,id'],
            'route_geometry' => ['sometimes', 'array', new GeoJsonLineString],
            'length_meters' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'installation_date' => ['sometimes', 'nullable', 'date'],
            'survey_source' => ['sometimes', 'nullable', 'string', 'max:100'],
            'surveyed_at' => ['sometimes', 'nullable', 'date'],
            'surveyed_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var FiberCable $cable */
            $cable = $this->route('fiberCable');
            $data = $this->validated();
            $companyId = $data['company_id'] ?? $cable->company_id;

            // Company ownership is immutable in FIM-002: reject silent ownership transfer.
            if (isset($data['company_id']) && (int) $data['company_id'] !== (int) $cable->company_id) {
                $validator->errors()->add('company_id', 'Cable company ownership cannot be changed.');
            }

            foreach (['start_site_id', 'end_site_id'] as $field) {
                if (array_key_exists($field, $data) && ! empty($data[$field])) {
                    $site = Site::find($data[$field]);
                    if ($site && (int) $site->company_id !== (int) $companyId) {
                        $validator->errors()->add($field, 'Endpoint sites must belong to the same company as the cable.');
                    }
                }
            }
        });
    }
}
