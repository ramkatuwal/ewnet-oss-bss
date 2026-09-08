<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSplitterProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('splitterProfile'));
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['missing'],
            'company_id' => ['missing'],
            'input_port_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
            'output_port_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
            'split_ratio' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
