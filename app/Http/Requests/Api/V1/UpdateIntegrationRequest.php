<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('integration'));
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'enabled' => ['boolean'],
            'configuration' => ['array'],
            'credential_type' => ['nullable', 'string', 'in:api_token,username_password,ssh_key,shared_secret,certificate,oauth,none'],
            'credential_value' => ['nullable', 'string', 'required_with:credential_type'],
            'credential_label' => ['nullable', 'string'],
        ];

        // Provider-specific configuration validation
        if ($this->input('configuration.api_url')) {
            $rules['configuration.api_url'] = ['url', 'starts_with:https://'];
        }

        return $rules;
    }
}
