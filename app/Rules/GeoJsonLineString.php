<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GeoJsonLineString implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be a GeoJSON LineString object.');

            return;
        }

        if (($value['type'] ?? null) !== 'LineString') {
            $fail('The :attribute must be a GeoJSON LineString.');

            return;
        }

        $coordinates = $value['coordinates'] ?? null;
        if (! is_array($coordinates) || count($coordinates) < 2) {
            $fail('The :attribute must contain at least two positions.');

            return;
        }

        foreach ($coordinates as $position) {
            if (! is_array($position) || count($position) !== 2) {
                $fail('The :attribute positions must each contain exactly longitude and latitude.');

                return;
            }

            [$lng, $lat] = array_values($position);

            // Reject numeric strings: coordinates must be actual JSON numbers.
            if (! is_int($lng) && ! is_float($lng)) {
                $fail('The :attribute longitude values must be numbers.');

                return;
            }
            if (! is_int($lat) && ! is_float($lat)) {
                $fail('The :attribute latitude values must be numbers.');

                return;
            }

            if (! is_finite((float) $lng) || ! is_finite((float) $lat)) {
                $fail('The :attribute coordinates must be finite numbers.');

                return;
            }

            if ((float) $lng < -180 || (float) $lng > 180) {
                $fail('The :attribute longitude must be between -180 and 180.');

                return;
            }
            if ((float) $lat < -90 || (float) $lat > 90) {
                $fail('The :attribute latitude must be between -90 and 90.');

                return;
            }
        }

        // Reject degenerate zero-length routes: all positions identical.
        $first = array_values($coordinates[0]);
        $allSame = true;
        foreach ($coordinates as $position) {
            $pos = array_values($position);
            if ((float) $pos[0] !== (float) $first[0] || (float) $pos[1] !== (float) $first[1]) {
                $allSame = false;
                break;
            }
        }
        if ($allSame) {
            $fail('The :attribute must form a non-zero-length route.');
        }
    }
}
