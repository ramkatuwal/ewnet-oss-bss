<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Company;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use App\Models\SplitterBranch;
use App\Models\SplitterProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SplitterBranchFactory extends Factory
{
    protected $model = SplitterBranch::class;

    public function definition(): array
    {
        return [
            'splitter_profile_id' => null,
            'asset_id' => null,
            'company_id' => null,
            'input_port_id' => null,
            'output_port_id' => null,
            'created_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SplitterBranch $branch) {
            if ($branch->splitter_profile_id !== null && $branch->asset_id !== null) {
                return;
            }

            $company = Company::factory()->create();
            $site = Site::factory()->create(['company_id' => $company->id]);
            $asset = Asset::factory()->create([
                'site_id' => $site->id,
                'company_id' => $company->id,
                'category' => 'INFRASTRUCTURE',
                'type' => 'SPLITTER',
            ]);
            $profile = SplitterProfile::factory()->create([
                'asset_id' => $asset->id,
                'company_id' => $company->id,
            ]);

            $inputPoint = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
            $inputPort = PassiveOpticalPort::factory()->create([
                'asset_id' => $asset->id,
                'network_connection_point_id' => $inputPoint->id,
                'company_id' => $company->id,
                'port_role' => 'splitter_input',
            ]);

            $outputPoint = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
            $outputPort = PassiveOpticalPort::factory()->create([
                'asset_id' => $asset->id,
                'network_connection_point_id' => $outputPoint->id,
                'company_id' => $company->id,
                'port_role' => 'splitter_output',
            ]);

            $branch->forceFill([
                'splitter_profile_id' => $profile->id,
                'asset_id' => $asset->id,
                'company_id' => $company->id,
                'input_port_id' => $inputPort->id,
                'output_port_id' => $outputPort->id,
            ]);
        });
    }
}
