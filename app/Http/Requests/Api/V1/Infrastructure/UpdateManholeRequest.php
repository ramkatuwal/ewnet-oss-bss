<?php

namespace App\Http\Requests\Api\V1\Infrastructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManholeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('manhole');
        return [
            'branch_id' => 'sometimes|exists:branches,id',
            'ward_id' => 'sometimes|exists:wards,id',
            'manhole_code' => ['sometimes', 'string', 'max:50', Rule::unique('manholes')->ignore($id)],
            'type' => 'sometimes|in:standard,deep,shallow,junction,other',
            'status' => 'sometimes|in:active,inactive,damaged,blocked',
            'condition' => 'sometimes|in:good,fair,poor,damaged,needs-repair',
            'depth' => 'nullable|numeric|min:0',
        ];
    }
}
