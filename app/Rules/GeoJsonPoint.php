<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GeoJsonPoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ($value['type'] ?? null) !== 'Point') {
            $fail('The :attribute must be a GeoJSON Point.');

            return;
        }

        $coordinates = $value['coordinates'] ?? null;
        if (! is_array($coordinates) || count($coordinates) !== 2) {
            $fail('The :attribute must contain exactly longitude and latitude.');

            return;
        }

        [$longitude, $latitude] = array_values($coordinates);
        if ((! is_int($longitude) && ! is_float($longitude)) || (! is_int($latitude) && ! is_float($latitude))
            || ! is_finite((float) $longitude) || ! is_finite((float) $latitude)
            || (float) $longitude < -180 || (float) $longitude > 180
            || (float) $latitude < -90 || (float) $latitude > 90) {
            $fail('The :attribute must contain finite longitude and latitude within WGS84 bounds.');
        }
    }
}
