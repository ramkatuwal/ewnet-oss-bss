<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('regions')->where(function ($query) {
                    return $query->where('company_id', $this->input('company_id'));
                })->ignore($this->route('region')),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('regions')->ignore($this->route('region')),
            ],
            'company_id' => 'required|exists:companies,id',
            'description' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A region with this name already exists in the company.',
            'code.unique' => 'A region with this code already exists.',
        ];
    }
}
