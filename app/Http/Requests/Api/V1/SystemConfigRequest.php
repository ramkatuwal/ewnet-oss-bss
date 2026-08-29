<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branding' => 'sometimes|array',
            'branding.app_name' => 'sometimes|string|max:255',
            'branding.browser_title' => 'sometimes|string|max:255',
            'branding.logo_path' => 'sometimes|string|max:255|nullable',
            'branding.favicon_path' => 'sometimes|string|max:255|nullable',
            'branding.login_branding' => 'sometimes|string|max:255',

            'header' => 'sometimes|array',
            'header.show_logo' => 'sometimes|boolean',
            'header.show_title' => 'sometimes|boolean',
            'header.show_user_menu' => 'sometimes|boolean',
            'header.show_notifications' => 'sometimes|boolean',

            'theme' => 'sometimes|array',
            'theme.compactness' => ['sometimes', Rule::in(['compact', 'comfortable', 'spacious'])],
            'theme.dark_mode' => 'sometimes|boolean',
            'theme.primary_color' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            
            'navigation' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'theme.compactness.in' => 'The compactness field must be compact, comfortable, or spacious.',
            'theme.primary_color.regex' => 'The primary color must be a valid hex color (e.g., #1976d2).',
        ];
    }
}
