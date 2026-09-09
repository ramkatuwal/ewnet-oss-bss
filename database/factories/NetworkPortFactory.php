<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Company;
use App\Models\NetworkPort;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkPortFactory extends Factory
{
    protected $model = NetworkPort::class;

    public function definition(): array
    {
        $site = Site::factory()->create();
        $company = Company::factory()->create();

        return [
            'asset_id' => Asset::factory()->create([
                'site_id' => $site->id,
                'company_id' => $company->id,
                'category' => 'NETWORK',
                'type' => $this->faker->randomElement(Asset::ACTIVE_TYPES),
            ]),
            'company_id' => $company->id,
            'port_key' => 'P'.$this->faker->unique()->numberBetween(1, 9999),
            'name' => $this->faker->optional()->word,
            'slot' => $this->faker->optional()->numberBetween(0, 10),
            'card' => $this->faker->optional()->numberBetween(0, 5),
            'port_number' => $this->faker->optional()->numberBetween(1, 48),
            'connector_type' => $this->faker->optional()->randomElement(['SC/APC', 'LC/APC', 'RJ45', 'SFP']),
            'port_direction' => $this->faker->optional()->randomElement(NetworkPort::PORT_DIRECTIONS),
            'metadata' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function forNetwork(): static
    {
        return $this->state(fn () => [
            'asset_id' => Asset::factory()->create(['category' => 'NETWORK', 'type' => $this->faker->randomElement(Asset::ACTIVE_TYPES)]),
        ]);
    }
}
