<?php

namespace App\Services\Fim;

use App\Models\Asset;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PassiveOpticalPortService
{
    public function create(Asset $asset, array $attributes, User $user): PassiveOpticalPort
    {
        return DB::transaction(function () use ($asset, $attributes, $user) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            $point = NetworkConnectionPoint::withTrashed()->lockForUpdate()->findOrFail($attributes['network_connection_point_id']);

            if ($asset->company_id === null
                || $asset->trashed()
                || $point->trashed()
                || (int) $point->company_id !== (int) $asset->company_id
            ) {
                throw ValidationException::withMessages(['network_connection_point_id' => 'The connection point must be active and belong to the asset company.']);
            }

            if (PassiveOpticalPort::withTrashed()->where('asset_id', $asset->id)->where('port_number', $attributes['port_number'])->exists()) {
                throw ValidationException::withMessages(['port_number' => 'This passive asset port number has already been used.']);
            }

            return PassiveOpticalPort::create([
                ...$attributes,
                'asset_id' => $asset->id,
                'company_id' => $asset->company_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function update(PassiveOpticalPort $port, array $attributes, User $user): PassiveOpticalPort
    {
        $port->update([...$attributes, 'updated_by' => $user->id]);

        return $port->fresh();
    }

    public function delete(PassiveOpticalPort $port): void
    {
        DB::transaction(fn () => $port->delete());
    }
}
