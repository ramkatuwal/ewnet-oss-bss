<?php

namespace App\Services\Network;

use App\Models\Asset;
use App\Models\NetworkPort;
use App\Models\PonDomain;
use App\Models\PonMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PonDomainService
{
    public function create(NetworkPort $port, User $user, ?array $metadata = null): PonDomain
    {
        return DB::transaction(function () use ($port, $user, $metadata) {
            $port = NetworkPort::withTrashed()->lockForUpdate()->findOrFail($port->id);
            $asset = Asset::withTrashed()->lockForUpdate()->findOrFail($port->asset_id);

            if ($port->trashed() || $asset->trashed()
                || strtoupper($asset->category) !== 'NETWORK'
                || strtoupper($asset->type) !== 'OLT'
                || $port->technology === null
                || (int) $port->company_id !== (int) $asset->company_id) {
                throw ValidationException::withMessages(['olt_port_id' => 'Select a live OLT port with technology on a same-company NETWORK/OLT asset.']);
            }

            if (PonDomain::where('olt_port_id', $port->id)->exists()) {
                throw ValidationException::withMessages(['olt_port_id' => 'This OLT port already has a live PON domain.']);
            }

            return PonDomain::create([
                'olt_port_id' => $port->id,
                'company_id' => $port->company_id,
                'metadata' => $metadata,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function delete(PonDomain $domain, User $user): void
    {
        DB::transaction(function () use ($domain, $user) {
            $domain = PonDomain::lockForUpdate()->findOrFail($domain->id);
            if (PonMembership::where('pon_domain_id', $domain->id)->exists()) {
                throw ValidationException::withMessages(['pon_domain' => 'Cannot retire a PON domain with live memberships.']);
            }
            $domain->update(['updated_by' => $user->id]);
            $domain->delete();
        });
    }

    private function rejectHistoryViolation(string $message): never
    {
        throw ValidationException::withMessages(['network_port' => $message]);
    }
}
