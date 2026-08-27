<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'region_id' => RegionFactory::new()->create()->id,
            'name' => $this->faker->company . ' Branch',
            'code' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{3}'),
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'state' => $this->faker->state,
            'country' => 'Nepal',
            'phone' => $this->faker->phoneNumber,
            'email' => $this->faker->companyEmail,
            'is_active' => true,
        ];
    }
}
