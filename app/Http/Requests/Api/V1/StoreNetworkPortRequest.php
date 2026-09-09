<?php

namespace App\Http\Requests\Api\V1;

use App\Models\NetworkPort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNetworkPortRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'port_key' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'slot' => ['nullable', 'string', 'max:50'],
            'card' => ['nullable', 'string', 'max:50'],
            'port_number' => ['nullable', 'string', 'max:50'],
            'connector_type' => ['nullable', 'string', 'max:50'],
            'port_direction' => ['nullable', 'string', Rule::in(NetworkPort::PORT_DIRECTIONS)],
            'technology' => ['nullable', 'string', Rule::in(NetworkPort::TECHNOLOGIES)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
