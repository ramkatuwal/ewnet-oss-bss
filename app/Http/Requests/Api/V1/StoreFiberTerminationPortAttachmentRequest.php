<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreFiberTerminationPortAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'passive_optical_port_id' => ['required', 'integer', 'exists:passive_optical_ports,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
