<?php

namespace App\Http\Requests\Api\V1;

class StoreCustomerOnboardingRequest extends StoreCustomerRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'source_id' => ['nullable', 'integer', 'exists:bss_sources,id'], 'initial_contact' => ['nullable', 'array'], 'initial_contact.kind' => ['required_with:initial_contact', 'in:email,phone,other'], 'initial_contact.value' => ['required_with:initial_contact', 'string', 'max:255'], 'initial_address' => ['nullable', 'array'], 'initial_address.line1' => ['required_with:initial_address', 'string', 'max:255'], 'initial_address.kind' => ['sometimes', 'in:billing,service,other'], 'business_profile' => ['nullable', 'array'], 'business_profile.legal_name' => ['nullable', 'string', 'max:255'], 'business_profile.registration_number' => ['nullable', 'string', 'max:128'], 'verification' => ['nullable', 'array'], 'verification.kind' => ['required_with:verification', 'string', 'max:64'], 'verification.status' => ['required_with:verification', 'in:pending,verified,rejected'], 'verification.reference' => ['nullable', 'string', 'max:128']];
    }
}
