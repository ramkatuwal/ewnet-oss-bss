<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ImportLibreNMSDevicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', $this->route('integration'));
    }

    public function rules(): array
    {
        return [
            'devices' => ['required', 'array', 'max:1000'],
            'devices.*' => ['array'],
            'devices.*.external_id' => ['required', 'string', 'max:255'],
        ];
    }
}
