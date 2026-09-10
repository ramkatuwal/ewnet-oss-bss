<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Company;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'exists:companies,id'], 'service_code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:internet,voice,iptv,other'], 'status' => ['sometimes', 'in:active,inactive'], 'description' => ['nullable', 'string'], 'metadata' => ['nullable', 'array'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited']];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $company = Company::find($this->integer('company_id'));
            if ($company && ! ManagementScopeService::isInScope($this->user(), $company)) {
                $validator->errors()->add('company_id', 'You do not have access to this company.');
            }
        });
    }
}
