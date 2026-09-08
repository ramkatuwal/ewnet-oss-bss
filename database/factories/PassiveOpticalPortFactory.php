<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Company;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class PassiveOpticalPortFactory extends Factory
{
    protected $model = PassiveOpticalPort::class;

    public function definition(): array
    {
        return [
            'asset_id' => null,
            'network_connection_point_id' => null,
            'company_id' => null,
            'port_number' => $this->faker->unique()->bothify('PORT-###'),
            'connector_type' => $this->faker->optional()->randomElement(['SC/APC', 'LC/APC']),
            'port_role' => 'generic',
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PassiveOpticalPort $port) {
            if ($port->asset_id !== null || $port->network_connection_point_id !== null || $port->company_id !== null) {
                return;
            }

            $company = Company::factory()->create();
            $site = Site::factory()->create(['company_id' => $company->id]);
            $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'ODF']);
            $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);

            $port->forceFill([
                'asset_id' => $asset->id,
                'network_connection_point_id' => $point->id,
                'company_id' => $company->id,
            ]);
        });
    }
}
