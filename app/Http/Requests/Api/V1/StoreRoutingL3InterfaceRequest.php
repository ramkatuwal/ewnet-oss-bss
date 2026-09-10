<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RoutingL3Interface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutingL3InterfaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'string', Rule::in(RoutingL3Interface::KINDS)],
            'network_port_id' => ['nullable', 'integer', 'exists:network_ports,id'],
            'vlan_id' => ['nullable', 'integer', 'exists:vlans,id'],
            'parent_routing_l3_interface_id' => ['nullable', 'integer', 'exists:routing_l3_interfaces,id'],
            'metadata' => ['nullable', 'array'],
            'routing_instance_id' => ['prohibited'], 'asset_id' => ['prohibited'], 'company_id' => ['prohibited'],
            'created_by' => ['prohibited'], 'updated_by' => ['prohibited'],
        ];
    }
}
