<?php

namespace App\Services\Network;

use App\Models\Asset;
use App\Models\NetworkPort;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NetworkPortService
{
    public function create(Asset $asset, array $attributes, User $user): NetworkPort
    {
        return DB::transaction(function () use ($asset, $attributes, $user) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);

            if ($asset->trashed()) {
                throw ValidationException::withMessages(['asset_id' => 'The asset must be active.']);
            }

            if (strtoupper($asset->category) !== 'NETWORK') {
                throw ValidationException::withMessages(['asset_id' => 'Network ports can only be created on NETWORK assets.']);
            }

            if ($asset->company_id === null) {
                throw ValidationException::withMessages(['asset_id' => 'The asset must have a company assigned.']);
            }

            if (NetworkPort::withTrashed()->where('asset_id', $asset->id)->where('port_key', $attributes['port_key'])->exists()) {
                throw ValidationException::withMessages(['port_key' => 'This port key already exists for this asset.']);
            }

            return NetworkPort::create([
                ...$attributes,
                'asset_id' => $asset->id,
                'company_id' => $asset->company_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function update(NetworkPort $port, array $attributes, User $user): NetworkPort
    {
        try {
            return DB::transaction(function () use ($port, $attributes, $user) {
                $port = NetworkPort::lockForUpdate()->findOrFail($port->id);

                if ($port->trashed()) {
                    throw ValidationException::withMessages(['network_port' => 'Cannot update a deleted network port.']);
                }

                if (NetworkPortFiberTerminationAttachment::withTrashed()->where('network_port_id', $port->id)->exists()
                    && collect(['asset_id', 'company_id', 'port_key'])->contains(fn ($key) => array_key_exists($key, $attributes) && (string) $attributes[$key] !== (string) $port->$key)) {
                    throw ValidationException::withMessages(['network_port' => 'Network port identity has fiber attachment history.']);
                }

                $port->update([...$attributes, 'updated_by' => $user->id]);

                return $port->fresh();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['network_port' => 'Cannot modify this network port due to data integrity constraints.']);
        }
    }

    public function delete(NetworkPort $port): void
    {
        try {
            DB::transaction(function () use ($port) {
                $port = NetworkPort::lockForUpdate()->findOrFail($port->id);

                // Protect port history: if any NCP references this port, prevent deletion.
                if (NetworkPortFiberTerminationAttachment::withTrashed()->where('network_port_id', $port->id)->exists()) {
                    throw ValidationException::withMessages(['network_port' => 'Cannot delete a network port with fiber attachment history.']);
                }
                if ($port->networkConnectionPoints()->exists()) {
                    throw ValidationException::withMessages(['network_port' => 'Cannot delete a network port that is referenced by connection points.']);
                }

                $port->delete();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['network_port' => 'Cannot modify or delete this network port due to data integrity constraints.']);
        }
    }
}
