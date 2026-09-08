<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Site;
use App\Models\SplitterProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SplitterProfileFactory extends Factory
{
    protected $model = SplitterProfile::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory()->state(function () {
                $company = Company::factory()->create();

                return [
                    'company_id' => $company->id,
                    'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
                    'category' => 'INFRASTRUCTURE',
                    'type' => 'SPLITTER',
                ];
            }),
            'company_id' => fn (array $attributes) => Asset::findOrFail($attributes['asset_id'])->company_id,
            'input_port_count' => 1,
            'output_port_count' => 8,
            'split_ratio' => null,
            'metadata' => null,
        ];
    }
}
