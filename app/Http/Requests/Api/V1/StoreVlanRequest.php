<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Company;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;

class StoreVlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vid' => ['required', 'integer', 'min:1', 'max:4094'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reserved' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $companyId = $this->integer('company_id');
            $company = Company::find($companyId);

            if ($company && ! ManagementScopeService::isInScope($this->user(), $company)) {
                $validator->errors()->add('company_id', 'You do not have permission to create VLANs for this company.');
            }
        });
    }
}
