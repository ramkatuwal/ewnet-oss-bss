<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiberTerminationPortAttachmentFactory extends Factory
{
    protected $model = FiberTerminationPortAttachment::class;

    public function definition(): array
    {
        return [
            'fiber_termination_id' => null,
            'passive_optical_port_id' => null,
            'company_id' => null,
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FiberTerminationPortAttachment $attachment) {
            if ($attachment->fiber_termination_id !== null || $attachment->passive_optical_port_id !== null || $attachment->company_id !== null) {
                return;
            }

            $company = Company::factory()->create();
            $site = Site::factory()->create(['company_id' => $company->id]);
            $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'ODF']);
            $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
            $otherEndpoint = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);
            $cable = FiberCable::factory()->create(['company_id' => $company->id]);
            $segment = FiberSegment::factory()->create(['fiber_cable_id' => $cable->id, 'company_id' => $company->id, 'endpoint_a_id' => $point->id, 'endpoint_b_id' => $otherEndpoint->id]);
            $core = FiberCore::factory()->create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id]);
            $termination = FiberTermination::create(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $point->id, 'segment_end' => 'A']);
            $port = PassiveOpticalPort::factory()->create(['asset_id' => $asset->id, 'network_connection_point_id' => $point->id, 'company_id' => $company->id]);

            $attachment->forceFill([
                'fiber_termination_id' => $termination->id,
                'passive_optical_port_id' => $port->id,
                'company_id' => $company->id,
            ]);
        });
    }
}
