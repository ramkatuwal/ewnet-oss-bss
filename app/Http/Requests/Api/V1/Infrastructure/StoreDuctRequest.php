<?php

namespace App\Http\Requests\Api\V1\Infrastructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDuctRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'branch_id' => 'required|exists:branches,id',
            'ward_id' => 'nullable|exists:wards,id',
            'duct_code' => 'required|string|max:50|unique:ducts,duct_code',
            'type' => 'required|in:underground,aerial,submarine,indoor,other',
            'material' => 'required|in:pvc,hdpe,steel,concrete,other',
            'diameter' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,damaged,blocked',
            'ownership' => 'sometimes|in:company,government,private,other',
        ];
    }
}
