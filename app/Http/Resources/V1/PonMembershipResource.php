<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PonMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pon_domain_id' => $this->pon_domain_id,
            'onu_asset_id' => $this->onu_asset_id,
            'onu_id' => $this->onu_id,
            'company_id' => $this->company_id,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'pon_domain' => new PonDomainResource($this->whenLoaded('ponDomain')),
            'onu_asset' => new AssetResource($this->whenLoaded('onuAsset')),
        ];
    }
}
