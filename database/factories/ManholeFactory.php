<?php

namespace Database\Factories;

use App\Models\Manhole;
use App\Models\Branch;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

class ManholeFactory extends Factory
{
    protected $model = Manhole::class;

    public function definition()
    {
        return [
            'branch_id' => Branch::factory(),
            'ward_id' => Ward::factory(),
            'manhole_code' => $this->faker->unique()->lexify('MH-????'),
            'type' => $this->faker->randomElement(['standard', 'deep', 'shallow', 'junction', 'other']),
            'status' => 'active',
            'condition' => $this->faker->randomElement(['good', 'fair', 'poor', 'damaged', 'needs-repair']),
            'depth' => $this->faker->optional()->randomFloat(2, 1, 15),
        ];
    }
}
