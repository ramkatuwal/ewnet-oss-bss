<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('branches')->where(function ($query) {
                    return $query->where('region_id', $this->input('region_id'));
                })->ignore($this->route('branch')),
            ],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('branches')->ignore($this->route('branch')),
            ],
            'region_id' => 'required|exists:regions,id',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|string|max:255',
            'longitude' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A branch with this name already exists in the region.',
            'code.unique' => 'A branch with this code already exists.',
        ];
    }
}
