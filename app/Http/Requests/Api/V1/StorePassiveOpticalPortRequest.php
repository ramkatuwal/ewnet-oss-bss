<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PassiveOpticalPort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePassiveOpticalPortRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'network_connection_point_id' => ['required', 'integer', 'exists:network_connection_points,id'],
            'port_number' => ['required', 'string', 'max:255'],
            'connector_type' => ['nullable', 'string', 'max:255'],
            'port_role' => ['required', Rule::in(PassiveOpticalPort::ROLES)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
