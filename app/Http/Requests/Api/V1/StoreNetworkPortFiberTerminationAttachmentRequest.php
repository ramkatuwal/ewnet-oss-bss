<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreNetworkPortFiberTerminationAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fiber_termination_id' => ['required', 'integer', 'exists:fiber_terminations,id'],
            'metadata' => ['nullable', 'array'],
            'network_port_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }
}
