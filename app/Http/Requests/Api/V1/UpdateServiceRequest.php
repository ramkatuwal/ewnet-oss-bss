<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'type' => ['sometimes', 'in:internet,voice,iptv,other'], 'status' => ['sometimes', 'in:active,inactive'], 'description' => ['sometimes', 'nullable', 'string'], 'metadata' => ['sometimes', 'nullable', 'array'], 'company_id' => ['prohibited'], 'service_code' => ['prohibited'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited'], 'deleted_at' => ['prohibited']];
    }
}
