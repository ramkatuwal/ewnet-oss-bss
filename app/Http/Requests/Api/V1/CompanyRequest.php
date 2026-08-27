<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // FormData sends booleans as strings; normalize them
        if ($this->has('is_active')) {
            $val = $this->input('is_active');
            $this->merge([
                'is_active' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('remove_logo')) {
            $val = $this->input('remove_logo');
            $this->merge([
                'remove_logo' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:512',
            'remove_logo' => 'nullable|boolean',
            'registration_number' => 'nullable|string|max:255|unique:companies,registration_number',
            'pan_number' => 'nullable|string|max:255|unique:companies,pan_number',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH') || $this->input('_method') === 'PUT') {
            $id = $this->route('company')?->id;
            if ($id) {
                $rules['registration_number'] .= ',' . $id;
                $rules['pan_number'] .= ',' . $id;
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required',
            'logo.image' => 'Logo must be an image file',
            'logo.mimes' => 'Logo must be PNG, JPG, SVG, or WebP',
            'logo.max' => 'Logo must not exceed 512 KB',
            'registration_number.unique' => 'This registration number is already in use',
            'pan_number.unique' => 'This PAN number is already in use',
            'email.email' => 'Please enter a valid email address',
        ];
    }
}
