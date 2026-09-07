<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberCore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFiberCoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fiberCore'));
    }

    public function rules(): array
    {
        /** @var FiberCore $core */
        $core = $this->route('fiberCore');

        return [
            'fiber_segment_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'core_number' => ['sometimes', 'integer', 'min:1', Rule::unique('fiber_cores')->ignore($core->id)->where('fiber_segment_id', $core->fiber_segment_id)],
            'status' => ['sometimes', 'string', 'in:'.implode(',', FiberCore::STATUSES)],
            'color_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
