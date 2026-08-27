<?php

namespace App\Http\Requests\Api\V1\Infrastructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'branch_id' => 'required|exists:branches,id',
            'ward_id' => 'required|exists:wards,id',
            'pole_code' => 'required|string|max:50|unique:poles,pole_code',
            'pole_number' => 'nullable|string|max:50',
            'type' => 'required|in:wood,concrete,steel,fiber,other',
            'material' => 'required|in:wood,concrete,steel,fiber,other',
            'height' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:active,inactive,damaged,removed',
            'installation_date' => 'nullable|date',
            'ownership' => 'sometimes|in:company,government,private,other',
        ];
    }
}
