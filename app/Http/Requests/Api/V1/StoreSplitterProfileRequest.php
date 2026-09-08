<?php

namespace App\Http\Requests\Api\V1;

use App\Models\SplitterProfile;
use Illuminate\Foundation\Http\FormRequest;

class StoreSplitterProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [SplitterProfile::class, $this->route('asset')]);
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['missing'],
            'company_id' => ['missing'],
            'input_port_count' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'output_port_count' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'split_ratio' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
