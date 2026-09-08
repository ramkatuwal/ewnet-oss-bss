<?php

namespace App\Services\Fim;

use App\Models\FiberTermination;
use App\Models\PhysicalConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhysicalConnectionService
{
    public function create(array $data, User $user): PhysicalConnection
    {
        return DB::transaction(function () use ($data, $user) {
            [$a,$b] = [min($data['termination_a_id'], $data['termination_b_id']), max($data['termination_a_id'], $data['termination_b_id'])];
            $terms = FiberTermination::whereIn('id', [$a, $b])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($terms->count() !== 2 || $terms[$a]->trashed() || $terms[$b]->trashed() || $terms[$a]->fiber_core_id === $terms[$b]->fiber_core_id || $terms[$a]->company_id !== $terms[$b]->company_id || $terms[$a]->network_connection_point_id !== $terms[$b]->network_connection_point_id || PhysicalConnection::whereNull('deleted_at')->where(fn ($q) => $q->whereIn('termination_a_id', [$a, $b])->orWhereIn('termination_b_id', [$a, $b]))->exists()) {
                throw ValidationException::withMessages(['termination_a_id' => 'The selected terminations cannot form a live splice.']);
            }

            return PhysicalConnection::create([...$data, 'termination_a_id' => $a, 'termination_b_id' => $b, 'company_id' => $terms[$a]->company_id, 'created_by' => $user->id, 'updated_by' => $user->id]);
        });
    }

    public function update(PhysicalConnection $c, array $d, User $u): PhysicalConnection
    {
        $c->update([...$d, 'updated_by' => $u->id]);

        return $c->fresh();
    }

    public function delete(PhysicalConnection $c): void
    {
        DB::transaction(fn () => $c->delete());
    }
}
