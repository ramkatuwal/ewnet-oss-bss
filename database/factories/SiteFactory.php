<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'site_code' => $this->faker->unique()->lexify('SITE-?????'),
            'name' => $this->faker->company . ' Site',
            'type' => $this->faker->randomElement(Site::TYPES),
            'status' => $this->faker->randomElement(Site::STATUSES),
            'description' => $this->faker->sentence,
            'notes' => $this->faker->paragraph,
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
            'altitude' => $this->faker->numberBetween(100, 3000),
            'province' => $this->faker->state,
            'district' => $this->faker->city,
            'municipality' => $this->faker->city,
            'ward' => $this->faker->numberBetween(1, 35),
            'tole' => $this->faker->word,
            'address' => $this->faker->address,
            'postal_code' => $this->faker->postcode,
            'company_id' => Company::factory(),
            'region_id' => null,
            'branch_id' => null,
        ];
    }

    public function withRegion(): static
    {
        return $this->state(function (array $attributes) {
            $company = Company::factory()->create();
            $region = Region::factory()->create(['company_id' => $company->id]);
            return [
                'company_id' => $company->id,
                'region_id' => $region->id,
            ];
        });
    }

    public function withBranch(): static
    {
        return $this->state(function (array $attributes) {
            $company = Company::factory()->create();
            $region = Region::factory()->create(['company_id' => $company->id]);
            $branch = Branch::factory()->create(['region_id' => $region->id]);
            return [
                'company_id' => $company->id,
                'region_id' => $region->id,
                'branch_id' => $branch->id,
            ];
        });
    }
}
