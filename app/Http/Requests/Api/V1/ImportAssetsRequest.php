<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ImportAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assets.import');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ];
    }
}
