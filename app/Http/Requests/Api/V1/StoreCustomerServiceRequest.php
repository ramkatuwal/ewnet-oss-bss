<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return ['service_id' => ['required', 'integer', 'exists:services,id'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'metadata' => ['nullable', 'array'], 'company_id' => ['prohibited'], 'customer_id' => ['prohibited'], 'status' => ['prohibited'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited']];
    }
}
