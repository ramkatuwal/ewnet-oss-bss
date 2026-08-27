<?php

namespace App\Http\Requests\Api\V1\Infrastructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDuctRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('duct');
        return [
            'branch_id' => 'sometimes|exists:branches,id',
            'ward_id' => 'nullable|exists:wards,id',
            'duct_code' => ['sometimes', 'string', 'max:50', Rule::unique('ducts')->ignore($id)],
            'type' => 'sometimes|in:underground,aerial,submarine,indoor,other',
            'material' => 'sometimes|in:pvc,hdpe,steel,concrete,other',
            'diameter' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive,damaged,blocked',
            'ownership' => 'sometimes|in:company,government,private,other',
        ];
    }
}
