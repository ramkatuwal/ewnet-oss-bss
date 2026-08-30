<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TransferAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assets.transfer');
    }

    public function rules(): array
    {
        return [
            'to_site_id' => ['required', 'exists:sites,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
