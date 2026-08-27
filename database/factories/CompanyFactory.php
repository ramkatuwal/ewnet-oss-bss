<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'registration_number' => $this->faker->unique()->numerify('###-###-###'),
            'pan_number' => $this->faker->unique()->numerify('#########'),
            'email' => $this->faker->companyEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'state' => $this->faker->state,
            'country' => 'Nepal',
            'is_active' => true,
        ];
    }
}
