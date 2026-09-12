<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Operational detail view for a single Asset.
 *
 * Used ONLY by the asset detail endpoint. The lean AssetResource remains the
 * list contract and never exposes operational sub-resources.
 */
class AssetOperationalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new AssetResource($this->resource))->toArray($request);

        return $base + [
            'interfaces' => AssetInterfaceResource::collection($this->whenLoaded('interfaces')),
            'ip_addresses' => IpAddressResource::collection($this->whenLoaded('ipAddresses')),
            'network_ports' => NetworkPortResource::collection($this->whenLoaded('networkPorts')),
            'routing_instances' => RoutingInstanceResource::collection($this->whenLoaded('routingInstances')),
            'passive_optical_ports' => PassiveOpticalPortResource::collection($this->whenLoaded('passiveOpticalPorts')),
            'splitter_profile' => new SplitterProfileResource($this->whenLoaded('splitterProfile')),
            'pon_memberships' => PonMembershipResource::collection($this->whenLoaded('ponMemberships')),
        ];
    }
}
