<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FiberCable;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class FiberCableFactory extends Factory
{
    protected $model = FiberCable::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'cable_code' => 'FC-'.$this->faker->unique()->lexify('?????-#####'),
            'name' => $this->faker->words(3, true).' fiber cable',
            'cable_type' => $this->faker->randomElement(FiberCable::CABLE_TYPES),
            'fiber_count' => $this->faker->randomElement([12, 24, 48, 96]),
            'status' => $this->faker->randomElement(FiberCable::STATUSES),
            'start_site_id' => null,
            'end_site_id' => null,
            'length_meters' => $this->faker->randomFloat(2, 10, 5000),
            'installation_date' => $this->faker->date(),
            'survey_source' => 'field_survey',
            'surveyed_at' => now(),
            'surveyed_by' => null,
            'metadata' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FiberCable $cable) {
            // Write route geometry with a bound parameter, matching the
            // request-path service. No raw JSON string interpolation.
            $geojson = $cable->getAttribute('route_geometry_geojson') ?? self::defaultRoute();
            DB::update(
                'UPDATE fiber_cables SET route_geometry = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?',
                [json_encode($geojson), $cable->id]
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultRoute(): array
    {
        return [
            'type' => 'LineString',
            'coordinates' => [
                [81.52572, 28.97354],
                [81.52610, 28.97400],
            ],
        ];
    }

    public function forCompanyWithSites(Company $company, ?Site $start = null, ?Site $end = null): static
    {
        return $this->state(fn () => [
            'company_id' => $company->id,
            'start_site_id' => $start?->id,
            'end_site_id' => $end?->id,
        ]);
    }
}
