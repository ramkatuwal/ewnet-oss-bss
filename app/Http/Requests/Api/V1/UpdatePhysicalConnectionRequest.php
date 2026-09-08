<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhysicalConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('physicalConnection'));
    }

    public function rules(): array
    {
        return ['company_id' => ['prohibited'], 'termination_a_id' => ['prohibited'], 'termination_b_id' => ['prohibited'], 'connection_type' => ['prohibited'], 'metadata' => ['sometimes', 'nullable', 'array']];
    }
}
