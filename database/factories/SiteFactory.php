<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Ward;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition()
    {
        $types = ['pop', 'data_center', 'exchange', 'cabinet', 'other'];
        $statuses = ['active', 'inactive', 'planned', 'decommissioned'];

        return [
            'code' => $this->faker->unique()->lexify('SITE-????'),
            'name' => $this->faker->company . ' Site',
            'type' => $this->faker->randomElement($types),
            'status' => $this->faker->randomElement($statuses),
            'address' => $this->faker->address,
            'description' => $this->faker->optional()->sentence,
            'ward_id' => Ward::factory(),
            'branch_id' => Branch::factory(),
            'company_id' => Company::factory(),
        ];
    }
}
