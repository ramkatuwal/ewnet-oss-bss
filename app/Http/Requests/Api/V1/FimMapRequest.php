<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FimMapRequest extends FormRequest
{
    private const MAX_SPAN_DEGREES = 10;

    public function rules(): array
    {
        return [
            'west' => ['required', 'numeric', 'between:-180,180'],
            'south' => ['required', 'numeric', 'between:-90,90'],
            'east' => ['required', 'numeric', 'between:-180,180'],
            'north' => ['required', 'numeric', 'between:-90,90'],
            'layers' => ['sometimes', 'array', 'min:1'],
            'layers.*' => ['string', Rule::in(['cables', 'points'])],
            'cable_status' => ['sometimes', 'string', 'max:50'],
            'point_status' => ['sometimes', 'string', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $west = (float) $this->input('west');
            $east = (float) $this->input('east');
            $south = (float) $this->input('south');
            $north = (float) $this->input('north');

            if ($west >= $east || $south >= $north) {
                $validator->errors()->add('bounds', 'Bounds must be ordered west, south, east, north.');
            }
            if (($east - $west) > self::MAX_SPAN_DEGREES || ($north - $south) > self::MAX_SPAN_DEGREES) {
                $validator->errors()->add('bounds', 'Viewport span must not exceed '.self::MAX_SPAN_DEGREES.' degrees.');
            }
        }];
    }
}
