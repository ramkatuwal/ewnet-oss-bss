<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePassiveOpticalPortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('passiveOpticalPort'));
    }

    public function rules(): array
    {
        return [
            'network_connection_point_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'asset_id' => ['prohibited'],
            'port_number' => ['prohibited'],
            'port_role' => ['prohibited'],
            'connector_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
