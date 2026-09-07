<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberCable;
use App\Models\NetworkConnectionPoint;
use App\Rules\GeoJsonLineString;
use App\Services\ManagementScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiberSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fim.fiber-segments.create');
    }

    public function rules(): array
    {
        return [
            'fiber_cable_id' => ['required', 'integer', 'exists:fiber_cables,id'],
            'endpoint_a_id' => ['required', 'integer', 'exists:network_connection_points,id'],
            'endpoint_b_id' => ['required', 'integer', 'different:endpoint_a_id', 'exists:network_connection_points,id'],
            'sequence' => ['required', 'integer', 'min:1', Rule::unique('fiber_segments')->where('fiber_cable_id', $this->input('fiber_cable_id'))],
            'geometry' => ['nullable', 'array', new GeoJsonLineString],
            'length_meters' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(FiberCable::STATUSES)],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $cable = FiberCable::find($this->input('fiber_cable_id'));
            $endpoints = NetworkConnectionPoint::whereIn('id', [
                $this->input('endpoint_a_id'),
                $this->input('endpoint_b_id'),
            ])->get();

            if (! $cable || ! ManagementScopeService::isInScope($this->user(), $cable)) {
                $validator->errors()->add('fiber_cable_id', 'You do not have access to this cable.');

                return;
            }

            foreach (['endpoint_a_id', 'endpoint_b_id'] as $field) {
                /** @var NetworkConnectionPoint|null $endpoint */
                $endpoint = $endpoints->firstWhere('id', $this->input($field));
                if (! $endpoint || (int) $endpoint->company_id !== (int) $cable->company_id) {
                    $validator->errors()->add($field, 'The endpoint must belong to the cable company.');
                } elseif (! ManagementScopeService::isInScope($this->user(), $endpoint)) {
                    $validator->errors()->add($field, 'You do not have access to this endpoint.');
                }
            }
        });
    }
}
