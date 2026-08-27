<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new()->create()->id,
            'branch_id' => BranchFactory::new()->create()->id,
            'name' => $this->faker->word . ' Department',
            'code' => $this->faker->unique()->regexify('[A-Z]{2}-[0-9]{2}'),
            'description' => $this->faker->sentence,
            'is_active' => true,
        ];
    }
}
