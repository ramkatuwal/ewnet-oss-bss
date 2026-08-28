<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $branch = Branch::factory()->create();

        return [
            'company_id' => $branch->region->company_id,
            'branch_id' => $branch->id,
            'name' => $this->faker->unique()->company() . ' Department',
            'code' => strtoupper($this->faker->unique()->bothify('DEP###')),
            'description' => $this->faker->sentence(),
            'settings' => [],
            'is_active' => true,
        ];
    }
}
