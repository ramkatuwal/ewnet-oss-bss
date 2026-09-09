<?php

namespace App\Services\Fim;

use App\Models\FiberCore;
use App\Models\FiberTermination;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiberTerminationService
{
    public function create(FiberCore $core, array $attributes, User $user): FiberTermination
    {
        return DB::transaction(function () use ($core, $attributes, $user) {
            $core = FiberCore::lockForUpdate()->findOrFail($core->id);
            if (FiberTermination::withTrashed()->where('fiber_core_id', $core->id)->where('segment_end', $attributes['segment_end'])->exists()) {
                throw ValidationException::withMessages(['segment_end' => 'This fiber core endpoint identity has already been used.']);
            }

            return FiberTermination::create([
                ...$attributes,
                'fiber_core_id' => $core->id,
                'company_id' => $core->company_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function update(FiberTermination $termination, array $attributes, User $user): FiberTermination
    {
        return DB::transaction(function () use ($termination, $attributes, $user) {
            $termination = FiberTermination::lockForUpdate()->findOrFail($termination->id);
            if (NetworkPortFiberTerminationAttachment::withTrashed()->where('fiber_termination_id', $termination->id)->exists()
                && collect(['fiber_core_id', 'company_id', 'segment_end', 'network_connection_point_id'])->contains(fn ($key) => array_key_exists($key, $attributes) && (string) $attributes[$key] !== (string) $termination->$key)) {
                throw ValidationException::withMessages(['fiber_termination' => 'Termination identity has network port attachment history.']);
            }
            FiberCore::lockForUpdate()->findOrFail($termination->fiber_core_id);
            $termination->update([...$attributes, 'updated_by' => $user->id]);

            return $termination->fresh();
        });
    }

    public function delete(FiberTermination $termination): void
    {
        DB::transaction(function () use ($termination) {
            $termination = FiberTermination::lockForUpdate()->findOrFail($termination->id);
            if (NetworkPortFiberTerminationAttachment::withTrashed()->where('fiber_termination_id', $termination->id)->exists()) {
                throw ValidationException::withMessages(['fiber_termination' => 'Cannot delete a termination with network port attachment history.']);
            }
            FiberCore::lockForUpdate()->findOrFail($termination->fiber_core_id);
            $termination->delete();
        });
    }
}
