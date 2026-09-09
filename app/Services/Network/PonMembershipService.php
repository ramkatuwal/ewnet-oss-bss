<?php

namespace App\Services\Network;

use App\Models\Asset;
use App\Models\NetworkPort;
use App\Models\PonDomain;
use App\Models\PonMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PonMembershipService
{
    public function create(PonDomain $domain, int $onuAssetId, User $user, ?string $onuId = null, ?array $metadata = null): PonMembership
    {
        return DB::transaction(function () use ($domain, $onuAssetId, $user, $onuId, $metadata) {
            $domain = PonDomain::withTrashed()->lockForUpdate()->findOrFail($domain->id);
            $onuAsset = Asset::withTrashed()->lockForUpdate()->findOrFail($onuAssetId);

            if ($domain->trashed()) {
                throw ValidationException::withMessages(['pon_domain_id' => 'Select a live PON domain.']);
            }

            $oltPort = NetworkPort::withTrashed()->lockForUpdate()->findOrFail($domain->olt_port_id);
            $oltAsset = Asset::withTrashed()->lockForUpdate()->findOrFail($oltPort->asset_id);

            if ($oltPort->trashed() || $oltAsset->trashed()
                || strtoupper($oltAsset->category) !== 'NETWORK'
                || strtoupper($oltAsset->type) !== 'OLT') {
                throw ValidationException::withMessages(['pon_domain_id' => 'PON domain owning OLT port or asset is not live.']);
            }

            if ($onuAsset->trashed()) {
                throw ValidationException::withMessages(['onu_asset_id' => 'Select a live ONU asset.']);
            }
            if (strtoupper($onuAsset->category) !== 'NETWORK' || strtoupper($onuAsset->type) !== 'ONU') {
                throw ValidationException::withMessages(['onu_asset_id' => 'ONU asset must be category NETWORK type ONU.']);
            }
            if ((int) $onuAsset->company_id !== (int) $domain->company_id) {
                throw ValidationException::withMessages(['onu_asset_id' => 'ONU asset and PON domain must share the same company.']);
            }

            if (PonMembership::where('onu_asset_id', $onuAsset->id)->exists()) {
                throw ValidationException::withMessages(['onu_asset_id' => 'This ONU already has a live PON membership.']);
            }

            if ($onuId !== null && PonMembership::where('pon_domain_id', $domain->id)->where('onu_id', $onuId)->exists()) {
                throw ValidationException::withMessages(['onu_id' => 'This ONU ID is already claimed on this PON domain.']);
            }

            return PonMembership::create([
                'pon_domain_id' => $domain->id,
                'onu_asset_id' => $onuAsset->id,
                'onu_id' => $onuId,
                'company_id' => $domain->company_id,
                'metadata' => $metadata,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function delete(PonMembership $membership, User $user): void
    {
        DB::transaction(function () use ($membership, $user) {
            $membership = PonMembership::lockForUpdate()->findOrFail($membership->id);
            $membership->update(['updated_by' => $user->id]);
            $membership->delete();
        });
    }
}
