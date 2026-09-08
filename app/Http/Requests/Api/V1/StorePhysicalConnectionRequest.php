<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PhysicalConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhysicalConnectionRequest extends FormRequest
{
    public function rules(): array
    {
        return ['termination_a_id' => ['required', 'integer', 'different:termination_b_id', 'exists:fiber_terminations,id'], 'termination_b_id' => ['required', 'integer', 'exists:fiber_terminations,id'], 'connection_type' => ['required', Rule::in(PhysicalConnection::TYPES)], 'metadata' => ['nullable', 'array']];
    }
}
