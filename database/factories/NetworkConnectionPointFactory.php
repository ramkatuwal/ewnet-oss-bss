<?php

namespace Database\Factories;

use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class NetworkConnectionPointFactory extends Factory
{
    protected $model = NetworkConnectionPoint::class;

    public function definition(): array
    {
        return [
            'point_type' => $this->faker->randomElement(['odf', 'cabinet', 'closure', 'splice_box', 'junction', 'db', 'other']),
            'name' => $this->faker->words(3, true),
            'site_id' => Site::factory(),
            'asset_id' => null,
            'company_id' => null,
            'status' => $this->faker->randomElement(['active', 'inactive', 'planned']),
            'metadata' => null,
        ];
    }

    public function withSite(): static
    {
        return $this->afterCreating(function (NetworkConnectionPoint $point) {
            $site = $point->site;
            if ($site) {
                $point->update(['company_id' => $site->company_id]);
            }
        });
    }

    public function standalone(): static
    {
        return $this->afterCreating(function (NetworkConnectionPoint $point) {
            // Write standalone surveyed geometry with a bound parameter.
            $geojson = self::defaultStandaloneGeometry();
            DB::update(
                'UPDATE network_connection_points SET geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?',
                [json_encode($geojson), $point->id]
            );
        });
    }

    public function withoutSite(): static
    {
        return $this->state(fn () => [
            'site_id' => null,
            'asset_id' => null,
        ]);
    }

    public static function defaultStandaloneGeometry(): array
    {
        return [
            'type' => 'Point',
            'coordinates' => [81.52572, 28.97354],
        ];
    }

    public static function defaultGeometry(): array
    {
        return [
            'type' => 'Point',
            'coordinates' => [81.52572, 28.97354],
        ];
    }
}
