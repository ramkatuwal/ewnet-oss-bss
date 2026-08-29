<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Integration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(Integration::TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'configuration.endpoint' => ['required_with:configuration', 'url', 'max:2000'],
            'configuration.port' => ['sometimes', 'integer', 'min:1, 'max:65535'],
            'configuration.timeout' => ['sometimes', 'integer', 'min:1, 'max:300'],
            'configuration.tls_verify' => ['sometimes', 'boolean'],
            'configuration.protocol' => ['sometimes', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The type must be one of: ' . implode(', ', Integration::TYPES),
            'configuration.endpoint.url' => 'The endpoint must be a valid URL.',
            'configuration.timeout.max' => 'Timeout cannot exceed 300 seconds.',
        ];
    }
}
