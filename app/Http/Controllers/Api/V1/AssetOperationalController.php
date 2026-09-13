<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AssetInterfaceResource;
use App\Http\Resources\V1\AssetVlanMembershipResource;
use App\Http\Resources\V1\IpAddressResource;
use App\Http\Resources\V1\ObservedVlanResource;
use App\Http\Resources\V1\PonMembershipResource;
use App\Models\Asset;
use App\Models\AssetInterface;
use App\Models\NetworkPort;
use App\Models\NetworkPortVlanMembership;
use App\Models\ObservedVlan;
use App\Models\Vlan;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only operational sub-views for the Asset detail workspace.
 *
 * These endpoints render observed/derived domain data from the canonical graph.
 * Reconciliation writes only explicit links to existing authoritative rows.
 */
class AssetOperationalController extends Controller
{
    public function reconcileInterface(Asset $asset, AssetInterface $assetInterface, Request $request)
    {
        $this->authorize('reconcileObservations', $asset);
        abort_unless($assetInterface->asset_id === $asset->id, 404);

        $data = $request->validate(['network_port_id' => ['required', 'integer', 'exists:network_ports,id']]);
        $port = NetworkPort::query()->where('id', $data['network_port_id'])->where('asset_id', $asset->id)->firstOrFail();

        $assetInterface->update(['reconciled_network_port_id' => $port->id]);
        AuditService::log('asset_interface.reconciled', 'success', $assetInterface, ['network_port_id' => $port->id]);

        return new AssetInterfaceResource($assetInterface->fresh(['ipAddresses', 'reconciledNetworkPort']));
    }

    public function unreconcileInterface(Asset $asset, AssetInterface $assetInterface)
    {
        $this->authorize('reconcileObservations', $asset);
        abort_unless($assetInterface->asset_id === $asset->id, 404);

        $assetInterface->update(['reconciled_network_port_id' => null]);
        AuditService::log('asset_interface.unreconciled', 'success', $assetInterface);

        return new AssetInterfaceResource($assetInterface->fresh(['ipAddresses', 'reconciledNetworkPort']));
    }

    public function reconcileVlan(Asset $asset, ObservedVlan $observedVlan, Request $request)
    {
        $this->authorize('reconcileObservations', $asset);
        abort_unless($observedVlan->asset_id === $asset->id, 404);

        $data = $request->validate(['vlan_id' => ['required', 'integer', 'exists:vlans,id']]);
        $vlan = Vlan::query()->where('id', $data['vlan_id'])->where('company_id', $asset->company_id)->firstOrFail();

        $observedVlan->update(['reconciled_vlan_id' => $vlan->id]);
        AuditService::log('observed_vlan.reconciled', 'success', $observedVlan, ['vlan_id' => $vlan->id]);

        return new ObservedVlanResource($observedVlan->fresh('reconciledVlan'));
    }

    public function unreconcileVlan(Asset $asset, ObservedVlan $observedVlan)
    {
        $this->authorize('reconcileObservations', $asset);
        abort_unless($observedVlan->asset_id === $asset->id, 404);

        $observedVlan->update(['reconciled_vlan_id' => null]);
        AuditService::log('observed_vlan.unreconciled', 'success', $observedVlan);

        return new ObservedVlanResource($observedVlan->fresh('reconciledVlan'));
    }

    public function interfaces(Asset $asset, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $asset);

        return AssetInterfaceResource::collection(
            $asset->interfaces()
                ->with(['ipAddresses', 'reconciledNetworkPort'])
                ->orderBy('name')
                ->paginate($request->integer('per_page', 50))
        );
    }

    public function ipAddresses(Asset $asset, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $asset);

        return IpAddressResource::collection(
            $asset->ipAddresses()
                ->with('interface')
                ->orderBy('ip_address')
                ->paginate($request->integer('per_page', 50))
        );
    }

    public function ponMemberships(Asset $asset, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $asset);

        return PonMembershipResource::collection(
            $asset->ponMemberships()
                ->with(['ponDomain.oltPort'])
                ->latest('updated_at')
                ->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * VLAN memberships aggregated per network port switching configuration.
     *
     * Returns a flat server-paginated list so the operator can scan "which VLANs
     * are assigned to this asset's ports" without N+1 client queries.
     */
    public function vlanMemberships(Asset $asset, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $asset);

        return AssetVlanMembershipResource::collection(
            NetworkPortVlanMembership::query()
                ->whereHas('switchingConfig.networkPort', function ($q) use ($asset) {
                    $q->where('asset_id', $asset->id);
                })
                ->with(['switchingConfig.networkPort', 'vlan'])
                ->latest('updated_at')
                ->paginate($request->integer('per_page', 50))
        );
    }

    /**
     * Observed (provider/discovery) VLANs for the asset, including the
     * authoritative `vlans` identity each observation was reconciled to.
     */
    public function observedVlans(Asset $asset, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $asset);

        return ObservedVlanResource::collection(
            $asset->observedVlans()
                ->with('reconciledVlan')
                ->orderBy('vid')
                ->paginate($request->integer('per_page', 50))
        );
    }
}
