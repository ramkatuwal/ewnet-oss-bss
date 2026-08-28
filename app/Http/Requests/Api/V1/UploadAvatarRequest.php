<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Users can always upload their own avatar
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'max:2048', // 2MB max
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.max' => 'The avatar may not be larger than 2MB.',
            'avatar.dimensions' => 'The avatar must be between 100x100 and 2000x2000 pixels.',
        ];
    }
}
