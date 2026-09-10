<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RoutingL3InterfaceAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutingL3InterfaceAddressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'regex:/^[^\/]+\/(?:[0-9]|[1-9][0-9]|1[01][0-9]|12[0-8])$/'],
            'address_role' => ['required', 'string', Rule::in(RoutingL3InterfaceAddress::ROLES)],
            'metadata' => ['nullable', 'array'],
            'routing_l3_interface_id' => ['prohibited'], 'routing_instance_id' => ['prohibited'], 'asset_id' => ['prohibited'], 'company_id' => ['prohibited'],
            'created_by' => ['prohibited'], 'updated_by' => ['prohibited'],
        ];
    }
}
