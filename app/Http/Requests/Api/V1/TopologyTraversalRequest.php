<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopologyTraversalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'direction' => ['sometimes', 'string', Rule::in(['both', 'downstream', 'trace-back'])],
            'max_depth' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'include_containment' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('include_containment')) {
            $this->merge([
                'include_containment' => filter_var($this->input('include_containment'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
