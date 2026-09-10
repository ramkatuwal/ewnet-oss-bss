<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReplaceNetworkPortSwitchingConfigRequest;
use App\Http\Resources\V1\NetworkPortSwitchingConfigResource;
use App\Models\NetworkPort;
use App\Models\NetworkPortSwitchingConfig;
use App\Models\NetworkPortVlanMembership;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Network\NetworkPortSwitchingConfigService;
use Illuminate\Http\Request;

class NetworkPortSwitchingConfigController extends Controller
{
    public function __construct(protected NetworkPortSwitchingConfigService $switching) {}

    public function show(Request $request, NetworkPort $networkPort)
    {
        $this->authorize('viewAny', NetworkPortSwitchingConfig::class);
        $this->authorize('view', $networkPort);
        $configuration = NetworkPortSwitchingConfig::where('network_port_id', $networkPort->id)->with(['networkPort.asset', 'memberships.vlan'])->firstOrFail();
        $this->authorize('view', $configuration);

        return (new NetworkPortSwitchingConfigResource($this->visibleMemberships($configuration, $request)))->response()->setStatusCode(200);
    }

    public function replace(ReplaceNetworkPortSwitchingConfigRequest $request, NetworkPort $networkPort)
    {
        $this->authorize('configure', [NetworkPortSwitchingConfig::class, $networkPort]);
        $result = $this->switching->replace($networkPort, $request->validated(), $request->user());
        $configuration = $result['configuration'];

        if ($result['changed']) {
            AuditService::log($result['replaced'] ? 'net.port-switching-config-replaced' : 'net.port-switching-configured', 'success', $configuration, $this->auditMetadata($configuration, $request->user()));
        }

        return (new NetworkPortSwitchingConfigResource($this->visibleMemberships($configuration, $request)))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, NetworkPort $networkPort)
    {
        $configuration = NetworkPortSwitchingConfig::where('network_port_id', $networkPort->id)->with('networkPort.asset')->firstOrFail();
        $this->authorize('delete', $configuration);
        $metadata = $this->auditMetadata($configuration->load('memberships.vlan'), $request->user());
        $this->switching->retire($networkPort, $request->user());
        AuditService::log('net.port-switching-config-retired', 'success', $configuration, $metadata);

        return response()->json(['message' => 'Port switching configuration retired successfully.']);
    }

    private function visibleMemberships(NetworkPortSwitchingConfig $configuration, Request $request): NetworkPortSwitchingConfig
    {
        $configuration->setRelation('memberships', $configuration->memberships->filter(
            fn (NetworkPortVlanMembership $membership) => $membership->vlan !== null && $request->user()->can('view', $membership->vlan)
        )->values());

        return $configuration;
    }

    private function auditMetadata(NetworkPortSwitchingConfig $configuration, ?User $user = null): array
    {
        return [
            'config_id' => $configuration->id,
            'network_port_id' => $configuration->network_port_id,
            'company_id' => $configuration->company_id,
            'mode' => $configuration->mode,
            'memberships' => $configuration->memberships->filter(
                fn (NetworkPortVlanMembership $membership) => $user === null
                    || ($membership->vlan !== null && $user->can('view', $membership->vlan))
            )->map(fn (NetworkPortVlanMembership $membership) => [
                'vlan_id' => $membership->vlan_id,
                'tagging' => $membership->tagging,
            ])->values()->all(),
        ];
    }
}
