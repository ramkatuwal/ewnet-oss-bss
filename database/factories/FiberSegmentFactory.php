<?php

namespace Database\Factories;

use App\Models\FiberCable;
use App\Models\FiberSegment;
use App\Models\NetworkConnectionPoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class FiberSegmentFactory extends Factory
{
    protected $model = FiberSegment::class;

    public function definition(): array
    {
        $cable = FiberCable::factory()->create();
        $endpointA = NetworkConnectionPoint::factory()->withoutSite()->create(['company_id' => $cable->company_id]);
        $endpointB = NetworkConnectionPoint::factory()->withoutSite()->create(['company_id' => $cable->company_id]);

        return [
            'fiber_cable_id' => $cable->id,
            'endpoint_a_id' => $endpointA->id,
            'endpoint_b_id' => $endpointB->id,
            'company_id' => $cable->company_id,
            'sequence' => 1,
            'length_meters' => $this->faker->randomFloat(2, 10, 5000),
            'status' => $this->faker->randomElement(FiberSegment::STATUSES),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FiberSegment $segment) {
            DB::update(
                'UPDATE fiber_segments SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?',
                [json_encode(self::defaultRoute()), $segment->id]
            );
        });
    }

    public static function defaultRoute(): array
    {
        return [
            'type' => 'LineString',
            'coordinates' => [[81.52572, 28.97354], [81.52610, 28.97400]],
        ];
    }
}
