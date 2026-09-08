<?php

namespace App\Services\Fim;

use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\PassiveOpticalPort;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiberTerminationPortAttachmentService
{
    public function attach(FiberTermination $termination, int $portId, User $user, ?array $metadata = null): FiberTerminationPortAttachment
    {
        return DB::transaction(function () use ($termination, $portId, $user, $metadata) {
            $termination = FiberTermination::withTrashed()->lockForUpdate()->findOrFail($termination->id);
            $port = PassiveOpticalPort::withTrashed()->lockForUpdate()->findOrFail($portId);

            if ($termination->trashed() || $port->trashed()) {
                throw ValidationException::withMessages(['passive_optical_port_id' => 'The termination and passive optical port must be active.']);
            }
            if ((int) $termination->company_id !== (int) $port->company_id) {
                throw ValidationException::withMessages(['passive_optical_port_id' => 'The passive optical port must belong to the termination company.']);
            }
            if ((int) $termination->network_connection_point_id !== (int) $port->network_connection_point_id) {
                throw ValidationException::withMessages(['passive_optical_port_id' => 'The passive optical port must use the termination connection point.']);
            }
            if (FiberTerminationPortAttachment::where('fiber_termination_id', $termination->id)->exists()) {
                throw ValidationException::withMessages(['fiber_termination_id' => 'The fiber termination already has a live passive optical port attachment.']);
            }
            if (FiberTerminationPortAttachment::where('passive_optical_port_id', $port->id)->exists()) {
                throw ValidationException::withMessages(['passive_optical_port_id' => 'The passive optical port already has a live fiber termination attachment.']);
            }

            return FiberTerminationPortAttachment::create([
                'fiber_termination_id' => $termination->id,
                'passive_optical_port_id' => $port->id,
                'company_id' => $termination->company_id,
                'created_by' => $user->id,
                'metadata' => $metadata,
            ]);
        });
    }

    public function detach(FiberTerminationPortAttachment $attachment): void
    {
        DB::transaction(function () use ($attachment) {
            $attachment = FiberTerminationPortAttachment::lockForUpdate()->findOrFail($attachment->id);
            $attachment->delete();
        });
    }
}
