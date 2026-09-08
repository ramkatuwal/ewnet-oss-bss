<?php

namespace App\Services\Fim;

use App\Models\FiberCore;
use App\Models\FiberTermination;
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
            FiberCore::lockForUpdate()->findOrFail($termination->fiber_core_id);
            $termination->update([...$attributes, 'updated_by' => $user->id]);

            return $termination->fresh();
        });
    }

    public function delete(FiberTermination $termination): void
    {
        DB::transaction(function () use ($termination) {
            FiberCore::lockForUpdate()->findOrFail($termination->fiber_core_id);
            $termination->delete();
        });
    }
}
