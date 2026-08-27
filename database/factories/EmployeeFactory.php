<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new()->create()->id,
            'company_id' => CompanyFactory::new()->create()->id,
            'region_id' => RegionFactory::new()->create()->id,
            'branch_id' => BranchFactory::new()->create()->id,
            'department_id' => DepartmentFactory::new()->create()->id,
            'designation_id' => DesignationFactory::new()->create()->id,
            'employee_id' => $this->faker->unique()->numerify('EMP-####'),
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->companyEmail,
            'phone' => $this->faker->phoneNumber,
            'date_of_joining' => $this->faker->date,
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'state' => $this->faker->state,
            'country' => 'Nepal',
            'is_active' => true,
        ];
    }
}
