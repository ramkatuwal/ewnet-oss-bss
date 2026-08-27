<?php

namespace Database\Factories;

use App\Models\Pole;
use App\Models\Branch;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoleFactory extends Factory
{
    protected $model = Pole::class;

    public function definition()
    {
        return [
            'branch_id' => Branch::factory(),
            'ward_id' => Ward::factory(),
            'pole_code' => $this->faker->unique()->lexify('POLE-????'),
            'pole_number' => $this->faker->optional()->bothify('###'),
            'type' => $this->faker->randomElement(['wood', 'concrete', 'steel', 'fiber', 'other']),
            'material' => $this->faker->randomElement(['wood', 'concrete', 'steel', 'fiber', 'other']),
            'height' => $this->faker->optional()->randomFloat(2, 5, 30),
            'status' => 'active',
            'ownership' => 'company',
        ];
    }
}
