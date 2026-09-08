<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberCore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiberTerminationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'network_connection_point_id' => ['required', 'integer', 'exists:network_connection_points,id'],
            'segment_end' => ['required', 'string', Rule::in(['A', 'B']), Rule::unique('fiber_terminations')->where('fiber_core_id', $this->route('fiberCore')->id)],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var FiberCore $core */
            $core = $this->route('fiberCore');
            $segment = $core->fiberSegment;
            $expected = $this->input('segment_end') === 'A' ? $segment->endpoint_a_id : $segment->endpoint_b_id;

            if ((int) $this->input('network_connection_point_id') !== (int) $expected) {
                $validator->errors()->add('network_connection_point_id', 'The connection point must match the selected fiber segment end.');
            }
        });
    }
}
