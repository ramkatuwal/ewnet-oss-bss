<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SplitterBranch;
use Illuminate\Foundation\Http\FormRequest;

class StoreSplitterBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [SplitterBranch::class, $this->route('splitterProfile')]);
    }

    public function rules(): array
    {
        return [
            'input_port_id' => ['required', 'integer', 'exists:passive_optical_ports,id'],
            'output_port_id' => ['required', 'integer', 'exists:passive_optical_ports,id', 'different:input_port_id'],
        ];
    }
}
