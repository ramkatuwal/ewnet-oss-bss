<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TransitionCustomerServiceRequest extends FormRequest
{
    public function rules(): array
    {
        return ['status' => ['required', 'in:active,suspended,terminated,cancelled']];
    }
}
