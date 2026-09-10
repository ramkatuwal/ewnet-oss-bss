<?php

namespace App\Http\Requests\Api\V1;

use App\Models\StaticRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaticRouteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', 'regex:/^[^\/]+\/(?:[0-9]|[1-9][0-9]|1[01][0-9]|12[0-8])$/'],
            'gateway' => ['nullable', 'string', 'regex:/^[^\/]+\/(?:[0-9]|[12][0-9]|3[0-2]|[1-9][0-9]|1[01][0-9]|12[0-8])$/'],
            'route_type' => ['required', 'string', Rule::in(StaticRoute::TYPES)],
            'routing_l3_interface_id' => ['nullable', 'integer', 'exists:routing_l3_interfaces,id'],
            'metadata' => ['nullable', 'array'],
            'routing_instance_id' => ['prohibited'], 'asset_id' => ['prohibited'], 'company_id' => ['prohibited'],
            'created_by' => ['prohibited'], 'updated_by' => ['prohibited'],
        ];
    }
}
