<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Models\NetworkPort;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\Network\NetworkPortFiberTerminationAttachmentService;

trait NetworkPortFiberFixtures
{
    protected function fixture(): array
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['company_id' => $company->id, 'site_id' => $site->id, 'category' => 'NETWORK', 'type' => 'OLT']);
        $port = NetworkPort::factory()->create(['company_id' => $company->id, 'asset_id' => $asset->id]);
        $point = NetworkConnectionPoint::factory()->create(['company_id' => $company->id, 'site_id' => $site->id]);
        $end = NetworkConnectionPoint::factory()->create(['company_id' => $company->id, 'site_id' => $site->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $segment = FiberSegment::create(['company_id' => $company->id, 'fiber_cable_id' => $cable->id, 'endpoint_a_id' => $point->id, 'endpoint_b_id' => $end->id, 'sequence' => 1, 'status' => 'installed']);
        $terms = [];
        foreach ([1, 2] as $number) {
            $core = FiberCore::create(['company_id' => $company->id, 'fiber_segment_id' => $segment->id, 'core_number' => $number, 'status' => 'available']);
            $terms[] = FiberTermination::create(['company_id' => $company->id, 'fiber_core_id' => $core->id, 'network_connection_point_id' => $point->id, 'segment_end' => 'A']);
        }
        $passiveAsset = Asset::factory()->create(['company_id' => $company->id, 'site_id' => $site->id, 'category' => 'INFRASTRUCTURE', 'type' => 'ODF']);
        $passive = PassiveOpticalPort::create(['asset_id' => $passiveAsset->id, 'company_id' => $company->id, 'network_connection_point_id' => $point->id, 'port_number' => 1, 'port_role' => 'generic']);
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        $user->givePermissionTo([
            'assets.view', 'net.network-ports.view', 'fim.cables.view', 'fim.fiber-segments.view',
            'fim.fiber-cores.view', 'fim.fiber-terminations.view', 'fim.connection-points.view',
            'fim.physical-connections.view', 'fim.passive-optical-ports.view', 'fim.termination-port-attachments.view',
            'net.network-port-fiber-attachments.view', 'net.network-port-fiber-attachments.create', 'net.network-port-fiber-attachments.delete',
        ]);

        return [$port, ...$terms, $passive, $user];
    }

    protected function attach(NetworkPort $port, FiberTermination $term, User $user): NetworkPortFiberTerminationAttachment
    {
        return app(NetworkPortFiberTerminationAttachmentService::class)->attach($port, $term->id, $user);
    }

    protected function activeRow(NetworkPort $port, FiberTermination $term): array
    {
        return ['network_port_id' => $port->id, 'fiber_termination_id' => $term->id, 'company_id' => $port->company_id];
    }

    protected function competitorRow(string $kind, FiberTermination $term, FiberTermination $other, PassiveOpticalPort $passive): array
    {
        return $kind === 'passive'
            ? ['fiber_termination_port_attachments', ['fiber_termination_id' => $term->id, 'passive_optical_port_id' => $passive->id, 'company_id' => $term->company_id]]
            : ['physical_connections', ['termination_a_id' => $term->id, 'termination_b_id' => $other->id, 'company_id' => $term->company_id, 'connection_type' => 'fusion_splice']];
    }
}
