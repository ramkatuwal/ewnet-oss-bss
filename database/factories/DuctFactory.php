<?php

namespace Database\Factories;

use App\Models\Duct;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class DuctFactory extends Factory
{
    protected $model = Duct::class;

    public function definition()
    {
        return [
            'branch_id' => Branch::factory(),
            'duct_code' => $this->faker->unique()->lexify('DUCT-????'),
            'type' => $this->faker->randomElement(['underground', 'aerial', 'submarine', 'indoor', 'other']),
            'material' => $this->faker->randomElement(['pvc', 'hdpe', 'steel', 'concrete', 'other']),
            'diameter' => $this->faker->optional()->randomFloat(2, 2, 50),
            'status' => 'active',
            'ownership' => 'company',
        ];
    }
}
