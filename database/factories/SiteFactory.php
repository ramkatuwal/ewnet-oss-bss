<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'site_code' => $this->faker->unique()->lexify('SC-?????-#####'),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['pop', 'tower', 'office', 'warehouse', 'datacenter', 'customer_premises']),
            'status' => $this->faker->randomElement(['planned', 'active', 'maintenance']),
            'latitude' => $this->faker->latitude(-90, 90),
            'longitude' => $this->faker->longitude(-180, 180),
            'company_id' => Company::factory(),
            'region_id' => null,
            'branch_id' => null,
        ];
    }
}
