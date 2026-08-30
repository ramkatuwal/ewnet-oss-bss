<?php

namespace Database\Factories;

use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' Integration',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'description' => $this->faker->sentence,
            'enabled' => true,
            'status' => 'connected',
            'configuration' => [
                'endpoint' => 'https://nms.example.com',
                'timeout' => 30,
            ],
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
