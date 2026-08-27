<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
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
                Rule::unique('departments')->where(function ($query) {
                    return $query->where('branch_id', $this->input('branch_id'));
                })->ignore($this->route('department')),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments')->ignore($this->route('department')),
            ],
            'branch_id' => 'required|exists:branches,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A department with this name already exists in the branch.',
            'code.unique' => 'A department with this code already exists.',
        ];
    }
}
