<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AssetInterfaceResource;
use App\Http\Resources\V1\AssetVlanMembershipResource;
use App\Http\Resources\V1\IpAddressResource;
use App\Http\Resources\V1\PonMembershipResource;
use App\Models\Asset;
use App\Models\NetworkPortVlanMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only operational sub-views for the Asset detail workspace.
 *
 * These endpoints render observed/derived domain data from the canonical graph.
 * Writing is NOT exposed here; intent is always authored through the domain APIs.
 */
class AssetOperationalController extends Controller
{
    public function interfaces(Asset $asset, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $asset);

        return AssetInterfaceResource::collection(
            $asset->interfaces()
                ->with('ipAddresses')
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
}
