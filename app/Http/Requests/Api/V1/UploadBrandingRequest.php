<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['logo', 'favicon'])],
            'file' => [
                'required',
                'file',
                'max:'.($this->input('type') === 'favicon' ? '512' : '1024'),
                Rule::when(
                    $this->input('type') === 'favicon',
                    [
                        'mimes:ico,png,svg,webp,jpg,jpeg',
                        'extensions:ico,png,svg,webp,jpg,jpeg',
                    ],
                    [
                        'mimes:png,jpg,jpeg,svg,webp',
                        'extensions:png,jpg,jpeg,svg,webp',
                    ]
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The branding type must be either logo or favicon.',
            'file.mimes' => 'Unsupported file type. Allowed: PNG, JPG, SVG, WEBP'.($this->input('type') === 'favicon' ? ', ICO' : '').'.',
            'file.extensions' => 'Unsupported file extension. Allowed: PNG, JPG, SVG, WEBP'.($this->input('type') === 'favicon' ? ', ICO' : '').'.',
            'file.max' => 'The file exceeds the maximum allowed size.',
        ];
    }
}
