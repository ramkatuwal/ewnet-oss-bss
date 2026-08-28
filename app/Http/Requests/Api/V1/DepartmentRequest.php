<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Validate that the actor has authority over the target branch
        $branchId = $this->input('branch_id');

        if ($branchId) {
            $companyId = $this->input('company_id');
            $tempDept = new \App\Models\Department([
                'branch_id' => $branchId,
                'company_id' => $companyId,
            ]);
            if (!ManagementScopeService::isInScope($user, $tempDept)) {
                return false;
            }
        }

        // For updates, also verify authority over existing resource
        if ($this->route('department')) {
            if (!ManagementScopeService::isInScope($user, $this->route('department'))) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        $deptId = $this->route('department')?->id;
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'name' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('departments')->ignore($deptId)->where(function ($query) {
                    $query->where('branch_id', $this->input('branch_id'));
                }),
            ],
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('departments')->ignore($deptId),
            ],
            'description' => 'sometimes|nullable|string|max:2000',
            'company_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                'exists:companies,id',
            ],
            'branch_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                'exists:branches,id',
            ],
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $branchId = $this->input('branch_id');
            $companyId = $this->input('company_id');

            if ($branchId && $companyId) {
                $branch = \App\Models\Branch::with('region')->find($branchId);
                if ($branch && $branch->region && $branch->region->company_id != $companyId) {
                    $validator->errors()->add('branch_id', 'The selected branch does not belong to the specified company.');
                }
            }
        });
    }
}
