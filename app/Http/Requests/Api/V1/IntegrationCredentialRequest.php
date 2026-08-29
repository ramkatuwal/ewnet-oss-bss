<?php

namespace App\Http\Requests\Api\V1;

use App\Models\IntegrationCredential;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntegrationCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credential_type' => ['required', Rule::in(IntegrationCredential::CREDENTIAL_TYPES)],
            'label' => ['nullable', 'string', 'max:255'],
            'value' => ['required_unless:credential_type,none', 'nullable', 'string', 'max:10000'],
            'metadata' => ['sometimes', 'array'],
            'metadata.username' => ['sometimes', 'string', 'max:255'],
            'metadata.key_id' => ['sometimes', 'string', 'max:255'],
            'metadata.cert_cn' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
