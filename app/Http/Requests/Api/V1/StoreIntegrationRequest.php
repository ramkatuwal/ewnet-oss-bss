<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Integration::class);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'in:librenms,uisp'],
            'type' => ['required', 'string', 'in:monitoring,aaa,network_device,access_network,dns,dhcp,logging,authentication,billing,other'],
            'description' => ['nullable', 'string'],
            'enabled' => ['boolean'],
            'configuration' => ['array'],
            'credential_type' => ['nullable', 'string', 'in:api_token,username_password,ssh_key,shared_secret,certificate,oauth,none'],
            'credential_value' => ['nullable', 'string', 'required_if:credential_type,api_token,username_password,ssh_key,shared_secret'],
            'credential_label' => ['nullable', 'string'],
        ];

        // Provider-specific configuration validation
        if ($this->input('provider') === 'uisp') {
            $rules['configuration.api_url'] = ['required', 'url', 'starts_with:https://'];
        } elseif ($this->input('provider') === 'librenms') {
            $rules['configuration.api_url'] = ['required', 'url', 'starts_with:https://'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'configuration.api_url.required' => 'The API URL is required for this provider.',
            'configuration.api_url.url' => 'The API URL must be a valid URL.',
            'configuration.api_url.starts_with' => 'The API URL must use HTTPS.',
            'credential_value.required_if' => 'A credential value is required for this authentication type.',
        ];
    }
}
