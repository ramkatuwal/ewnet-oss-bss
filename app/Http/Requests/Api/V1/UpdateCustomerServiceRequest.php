<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return ['starts_on' => ['sometimes', 'nullable', 'date'], 'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'], 'metadata' => ['sometimes', 'nullable', 'array'], 'company_id' => ['prohibited'], 'customer_id' => ['prohibited'], 'service_id' => ['prohibited'], 'status' => ['prohibited'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited']];
    }
}
