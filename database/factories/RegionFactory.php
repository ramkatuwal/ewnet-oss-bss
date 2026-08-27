<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new()->create()->id,
            'name' => $this->faker->city . ' Region',
            'code' => $this->faker->unique()->regexify('[A-Z]{3}'),
            'description' => $this->faker->sentence,
            'city' => $this->faker->city,
            'state' => $this->faker->state,
            'country' => 'Nepal',
            'is_active' => true,
        ];
    }
}
