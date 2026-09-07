<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiberCoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FiberCore::class);
    }

    public function rules(): array
    {
        return [
            'fiber_segment_id' => ['required', 'integer', 'exists:fiber_segments,id'],
            'core_number' => ['required', 'integer', 'min:1', Rule::unique('fiber_cores')->where('fiber_segment_id', $this->input('fiber_segment_id'))],
            'status' => ['required', 'string', 'in:'.implode(',', FiberCore::STATUSES)],
            'color_code' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $segment = FiberSegment::find($this->input('fiber_segment_id'));

            if ($segment && ! ManagementScopeService::isInScope($this->user(), $segment)) {
                $validator->errors()->add('fiber_segment_id', 'You do not have access to this fiber segment.');
            }
        });
    }
}
