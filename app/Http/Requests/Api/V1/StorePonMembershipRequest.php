<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePonMembershipRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'onu_asset_id' => ['required', 'integer', 'exists:assets,id'],
            'onu_id' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'pon_domain_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }
}
