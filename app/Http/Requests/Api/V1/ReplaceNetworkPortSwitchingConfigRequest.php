<?php

namespace App\Http\Requests\Api\V1;

use App\Models\NetworkPortSwitchingConfig;
use App\Models\Vlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceNetworkPortSwitchingConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(NetworkPortSwitchingConfig::MODES)],
            'metadata' => ['nullable', 'array'],
            'memberships' => ['present', 'array'],
            'memberships.*.vlan_id' => ['required', 'integer', 'distinct', 'exists:vlans,id'],
            'memberships.*.tagging' => ['required', 'string', Rule::in(['tagged', 'untagged'])],
            'memberships.*.metadata' => ['nullable', 'array'],
            'company_id' => ['prohibited'],
            'network_port_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('memberships', []) as $index => $membership) {
                $vlan = isset($membership['vlan_id']) ? Vlan::find($membership['vlan_id']) : null;
                if (! $vlan || ! $this->user()->can('view', $vlan)) {
                    $validator->errors()->add("memberships.{$index}.vlan_id", 'The selected VLAN is unavailable.');
                }
            }
        });
    }
}
