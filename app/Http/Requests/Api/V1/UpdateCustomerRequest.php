<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'type' => ['sometimes', 'in:individual,organization'], 'status' => ['sometimes', 'in:active,inactive'], 'email' => ['sometimes', 'nullable', 'email', 'max:255'], 'phone' => ['sometimes', 'nullable', 'string', 'max:64'], 'address' => ['sometimes', 'nullable', 'string'], 'metadata' => ['sometimes', 'nullable', 'array'], 'company_id' => ['prohibited'], 'customer_code' => ['prohibited'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited'], 'deleted_at' => ['prohibited']];
    }
}
