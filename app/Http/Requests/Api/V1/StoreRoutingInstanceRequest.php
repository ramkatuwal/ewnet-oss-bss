<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RoutingInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutingInstanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/\\S/'],
            'kind' => ['required', 'string', Rule::in(RoutingInstance::KINDS)],
            'metadata' => ['nullable', 'array'],
            'asset_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }
}
