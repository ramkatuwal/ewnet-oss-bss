<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\SystemConfigService;

class SystemConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedKeys = array_keys(SystemConfigService::getAllowedKeys());

        $rules = [];

        // Build dynamic rules for each config group
        $groups = SystemConfigService::getGroups();
        foreach ($groups as $group) {
            $rules[$group] = 'sometimes|array';
        }

        // Specific field rules
        $rules['branding.app_name'] = 'sometimes|string|max:255';
        $rules['branding.browser_title'] = 'sometimes|string|max:255';
        $rules['branding.logo_path'] = 'sometimes|string|max:255|nullable';
        $rules['branding.favicon_path'] = 'sometimes|string|max:255|nullable';
        $rules['branding.login_branding'] = 'sometimes|string|max:255';

        $rules['header.show_logo'] = 'sometimes|boolean';
        $rules['header.show_title'] = 'sometimes|boolean';
        $rules['header.show_user_menu'] = 'sometimes|boolean';
        $rules['header.show_notifications'] = 'sometimes|boolean';

        $rules['theme.compactness'] = 'sometimes|in:compact,comfortable,spacious';
        $rules['theme.dark_mode'] = 'sometimes|boolean';
        $rules['theme.primary_color'] = 'sometimes|regex:/^#[0-9a-fA-F]{6}$/';

        return $rules;
    }

    public function messages(): array
    {
        return [
            'branding.app_name.string' => 'Application name must be a string',
            'branding.app_name.max' => 'Application name must not exceed 255 characters',
            'branding.browser_title.string' => 'Browser title must be a string',
            'branding.browser_title.max' => 'Browser title must not exceed 255 characters',
            'branding.logo_path.string' => 'Logo path must be a string',
            'branding.favicon_path.string' => 'Favicon path must be a string',
            'branding.login_branding.string' => 'Login branding must be a string',
            'branding.login_branding.max' => 'Login branding must not exceed 255 characters',
            'header.show_logo.boolean' => 'Show logo must be true or false',
            'header.show_title.boolean' => 'Show title must be true or false',
            'header.show_user_menu.boolean' => 'Show user menu must be true or false',
            'header.show_notifications.boolean' => 'Show notifications must be true or false',
            'theme.compactness.in' => 'Compactness must be compact, comfortable, or spacious',
            'theme.dark_mode.boolean' => 'Dark mode must be true or false',
            'theme.primary_color.regex' => 'Primary color must be a valid hex color (e.g., #1976d2)',
        ];
    }
}
