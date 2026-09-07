<?php

namespace Database\Factories;

use App\Models\FiberCore;
use App\Models\FiberSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiberCoreFactory extends Factory
{
    protected $model = FiberCore::class;

    public function definition(): array
    {
        $segment = FiberSegment::factory()->create();

        return [
            'fiber_segment_id' => $segment->id,
            'company_id' => $segment->company_id,
            'core_number' => 1,
            'status' => $this->faker->randomElement(FiberCore::STATUSES),
            'color_code' => null,
            'metadata' => null,
        ];
    }
}
