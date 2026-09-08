<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberTermination;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFiberTerminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fiberTermination'));
    }

    public function rules(): array
    {
        return [
            'fiber_core_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'segment_end' => ['prohibited'],
            'network_connection_point_id' => ['sometimes', 'integer', 'exists:network_connection_points,id'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var FiberTermination $termination */
            $termination = $this->route('fiberTermination');
            $expected = $termination->segment_end === 'A'
                ? $termination->fiberCore->fiberSegment->endpoint_a_id
                : $termination->fiberCore->fiberSegment->endpoint_b_id;

            if ($this->filled('network_connection_point_id') && (int) $this->input('network_connection_point_id') !== (int) $expected) {
                $validator->errors()->add('network_connection_point_id', 'The connection point must match the selected fiber segment end.');
            }
        });
    }
}
