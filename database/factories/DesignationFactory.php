<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DesignationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->jobTitle,
            'code' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{2}'),
            'description' => $this->faker->sentence,
            'level' => $this->faker->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
