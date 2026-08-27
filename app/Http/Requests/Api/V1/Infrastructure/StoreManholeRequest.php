<?php

namespace App\Http\Requests\Api\V1\Infrastructure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManholeRequest extends FormRequest
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
            'manhole_code' => 'required|string|max:50|unique:manholes,manhole_code',
            'type' => 'required|in:standard,deep,shallow,junction,other',
            'status' => 'sometimes|in:active,inactive,damaged,blocked',
            'condition' => 'sometimes|in:good,fair,poor,damaged,needs-repair',
            'depth' => 'nullable|numeric|min:0',
        ];
    }
}
