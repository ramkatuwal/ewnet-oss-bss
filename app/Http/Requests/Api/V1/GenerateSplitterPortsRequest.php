<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSplitterPortsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('generatePorts', $this->route('splitterProfile'));
    }

    public function rules(): array
    {
        return [
            'inputs' => ['required', 'array', 'list'],
            'outputs' => ['required', 'array', 'list'],
            'inputs.*' => ['required', 'array:port_number,network_connection_point_id'],
            'outputs.*' => ['required', 'array:port_number,network_connection_point_id'],
            'inputs.*.port_number' => ['required', 'string', 'max:255'],
            'outputs.*.port_number' => ['required', 'string', 'max:255'],
            // Existence and visibility are checked together under lock, before matching diagnostics.
            'inputs.*.network_connection_point_id' => ['required', 'integer', 'min:1'],
            'outputs.*.network_connection_point_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
