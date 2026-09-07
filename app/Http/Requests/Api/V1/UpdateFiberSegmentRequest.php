<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberCable;
use App\Models\FiberSegment;
use App\Models\NetworkConnectionPoint;
use App\Rules\GeoJsonLineString;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFiberSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fiberSegment'));
    }

    public function rules(): array
    {
        /** @var FiberSegment $segment */
        $segment = $this->route('fiberSegment');

        return [
            'fiber_cable_id' => ['prohibited'],
            'endpoint_a_id' => ['sometimes', 'integer', 'exists:network_connection_points,id'],
            'endpoint_b_id' => ['sometimes', 'integer', 'exists:network_connection_points,id'],
            'sequence' => ['sometimes', 'integer', 'min:1', Rule::unique('fiber_segments')->ignore($segment->id)->where('fiber_cable_id', $segment->fiber_cable_id)],
            'geometry' => ['sometimes', 'nullable', 'array', new GeoJsonLineString],
            'length_meters' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(FiberCable::STATUSES)],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var FiberSegment $segment */
            $segment = $this->route('fiberSegment');
            $cable = $segment->fiberCable;
            $endpointA = NetworkConnectionPoint::find($this->input('endpoint_a_id', $segment->endpoint_a_id));
            $endpointB = NetworkConnectionPoint::find($this->input('endpoint_b_id', $segment->endpoint_b_id));

            if ($endpointA && $endpointB && $endpointA->id === $endpointB->id) {
                $validator->errors()->add('endpoint_b_id', 'The endpoints must be different connection points.');
            }

            foreach (['endpoint_a_id' => $endpointA, 'endpoint_b_id' => $endpointB] as $field => $endpoint) {
                if (! $endpoint || (int) $endpoint->company_id !== (int) $cable->company_id) {
                    $validator->errors()->add($field, 'The endpoint must belong to the cable company.');
                } elseif (! ManagementScopeService::isInScope($this->user(), $endpoint)) {
                    $validator->errors()->add($field, 'You do not have access to this endpoint.');
                }
            }
        });
    }
}
