<?php

namespace App\Http\Requests\Api\V1\Infrastructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('pole');
        return [
            'branch_id' => 'sometimes|exists:branches,id',
            'ward_id' => 'sometimes|exists:wards,id',
            'pole_code' => ['sometimes', 'string', 'max:50', Rule::unique('poles')->ignore($id)],
            'pole_number' => 'nullable|string|max:50',
            'type' => 'sometimes|in:wood,concrete,steel,fiber,other',
            'material' => 'sometimes|in:wood,concrete,steel,fiber,other',
            'height' => 'nullable|numeric|min:0|max:100',
            'status' => 'sometimes|in:active,inactive,damaged,removed',
            'installation_date' => 'nullable|date',
            'ownership' => 'sometimes|in:company,government,private,other',
        ];
    }
}
