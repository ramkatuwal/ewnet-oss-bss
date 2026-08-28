<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Validate organizational scope limits
        $companyId = $this->input('company_id') ?? $user->company_id;
        $branchId = $this->input('branch_id') ?? $user->branch_id;
        $deptId = $this->input('department_id') ?? $user->department_id;

        // Cannot escape own company
        if ($companyId !== $user->company_id) {
            return false;
        }

        // Cannot escape own branch
        if ($user->branch_id && $branchId !== $user->branch_id) {
            return false;
        }

        // Cannot escape own department
        if ($user->department_id && $deptId !== $user->department_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : null;
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        $nameRule = $isUpdate ? 'sometimes|string|max:255' : 'required|string|max:255';
        $emailRule = $isUpdate 
            ? ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($userId)]
            : ['required', 'email', 'max:255', Rule::unique('users')];
        $passwordRule = $isUpdate ? 'sometimes|string|min:8' : 'required|string|min:8';

        return [
            'name' => $nameRule,
            'phone_number' => ['nullable', 'string', 'max:255'],
            'email' => $emailRule,
            'password' => $passwordRule,
            'company_id' => 'sometimes|exists:companies,id',
            'branch_id' => 'sometimes|exists:branches,id',
            'department_id' => 'sometimes|exists:departments,id',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,id',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            
            // Validate hierarchy consistency if provided
            if (isset($data['branch_id']) && isset($data['company_id'])) {
                $branch = \App\Models\Branch::find($data['branch_id']);
                if (!$branch || $branch->region->company_id !== $data['company_id']) {
                    $validator->errors()->add('branch_id', 'The branch must belong to the specified company.');
                }
            }

            if (isset($data['department_id']) && isset($data['branch_id'])) {
                $dept = \App\Models\Department::find($data['department_id']);
                if (!$dept || $dept->branch_id !== $data['branch_id']) {
                    $validator->errors()->add('department_id', 'The department must belong to the specified branch.');
                }
            }
        });
    }
}
