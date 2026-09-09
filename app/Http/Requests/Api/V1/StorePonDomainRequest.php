<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePonDomainRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'metadata' => ['nullable', 'array'],
            'olt_port_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }
}
