<?php

namespace App\Http\Requests\Api\V1;

use App\Models\NetworkPort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNetworkPortRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slot' => ['sometimes', 'nullable', 'string', 'max:50'],
            'card' => ['sometimes', 'nullable', 'string', 'max:50'],
            'port_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'connector_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'port_direction' => ['sometimes', 'nullable', 'string', Rule::in(NetworkPort::PORT_DIRECTIONS)],
            'technology' => ['sometimes', 'nullable', 'string', Rule::in(NetworkPort::TECHNOLOGIES)],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
