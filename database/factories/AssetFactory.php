<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(Asset::CATEGORIES);
        $type = match ($category) {
            'POWER' => $this->faker->randomElement(['Battery', 'UPS', 'Solar Panel', 'Inverter']),
            'NETWORK' => $this->faker->randomElement(['Router', 'Switch', 'OLT', 'Firewall']),
            'INFRASTRUCTURE' => $this->faker->randomElement(['Rack', 'Cabinet', 'PDU', 'AC']),
            default => 'Other',
        };

        return [
            'site_id' => Site::factory(),
            'asset_tag' => 'EW-' . strtoupper($category) . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'serial_number' => $this->faker->optional(0.7)->lexify('SN??????????'),
            'category' => $category,
            'type' => $type,
            'manufacturer' => $this->faker->company,
            'model' => $this->faker->word . '-' . $this->faker->numberBetween(100, 999),
            'quantity' => $this->faker->numberBetween(1, 50),
            'unit' => 'pcs',
            'status' => $this->faker->randomElement(Asset::STATUSES),
            'condition' => $this->faker->randomElement(Asset::CONDITIONS),
            'purchase_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'installation_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'warranty_expiry' => $this->faker->dateTimeBetween('now', '+2 years'),
            'specifications' => ['voltage' => $this->faker->randomElement([12, 24, 48]) . 'V'],
            'description' => $this->faker->sentence,
            'notes' => $this->faker->paragraph,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
