<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StrandPathRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', 'string', Rule::in(['physical-strand', 'same-cable-strand'])],
            'max_depth' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
