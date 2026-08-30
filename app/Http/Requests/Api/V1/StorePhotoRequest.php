<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'max:5120'], // 5MB max
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'taken_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.max' => 'The photo must not be larger than 5MB.',
            'photo.image' => 'The file must be an image.',
        ];
    }
}
