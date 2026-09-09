<?php

namespace App\Services\Network;

use App\Models\Asset;
use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\NetworkConnectionPoint;
use App\Models\NetworkPort;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Models\PhysicalConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NetworkPortFiberTerminationAttachmentService
{
    public function attach(NetworkPort $port, int $terminationId, User $user, ?array $metadata = null): NetworkPortFiberTerminationAttachment
    {
        return DB::transaction(function () use ($port, $terminationId, $user, $metadata) {
            $termination = FiberTermination::withTrashed()->lockForUpdate()->findOrFail($terminationId);
            // NCP updates own their tuple before the network_port_id FK locks the port.
            $point = NetworkConnectionPoint::withTrashed()->lockForUpdate()->findOrFail($termination->network_connection_point_id);
            $port = NetworkPort::withTrashed()->lockForUpdate()->findOrFail($port->id);
            $asset = Asset::withTrashed()->lockForUpdate()->findOrFail($port->asset_id);

            if ($termination->trashed() || $port->trashed() || $asset->trashed()
                || strtoupper($asset->category) !== 'NETWORK'
                || (int) $termination->company_id !== (int) $port->company_id
                || (int) $asset->company_id !== (int) $port->company_id
                || ($point->network_port_id !== null && (int) $point->network_port_id !== (int) $port->id)) {
                throw ValidationException::withMessages(['fiber_termination_id' => 'Select live same-company endpoints on a NETWORK asset without a conflicting explicit connection point port.']);
            }
            if (NetworkPortFiberTerminationAttachment::where('network_port_id', $port->id)->exists()
                || NetworkPortFiberTerminationAttachment::where('fiber_termination_id', $termination->id)->exists()
                || FiberTerminationPortAttachment::where('fiber_termination_id', $termination->id)->exists()
                || PhysicalConnection::where(fn ($q) => $q->where('termination_a_id', $termination->id)->orWhere('termination_b_id', $termination->id))->exists()) {
                throw ValidationException::withMessages(['fiber_termination_id' => 'The port or termination already has a live external connection.']);
            }

            return NetworkPortFiberTerminationAttachment::create([
                'network_port_id' => $port->id,
                'fiber_termination_id' => $termination->id,
                'company_id' => $port->company_id,
                'metadata' => $metadata,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function disconnect(NetworkPortFiberTerminationAttachment $attachment, User $user): void
    {
        DB::transaction(function () use ($attachment, $user) {
            // Match raw UPDATE: attachment tuple first, then endpoint locks in the trigger.
            $attachment = NetworkPortFiberTerminationAttachment::lockForUpdate()->findOrFail($attachment->id);
            $attachment->update(['updated_by' => $user->id]);
            $attachment->delete();
        });
    }
}
