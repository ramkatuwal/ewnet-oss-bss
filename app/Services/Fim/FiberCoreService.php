<?php

namespace App\Services\Fim;

use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiberCoreService
{
    public function create(array $attributes, User $user): FiberCore
    {
        return DB::transaction(function () use ($attributes, $user) {
            $segment = $this->lockedSegment($attributes['fiber_segment_id']);
            $this->ensureNumberIsAvailable($segment->id, $attributes['core_number']);

            return FiberCore::create([
                ...$attributes,
                'company_id' => $segment->company_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });
    }

    public function update(FiberCore $core, array $attributes, User $user): FiberCore
    {
        return DB::transaction(function () use ($core, $attributes, $user) {
            $this->lockedSegment($core->fiber_segment_id);
            if (array_key_exists('core_number', $attributes)) {
                $this->ensureNumberIsAvailable($core->fiber_segment_id, $attributes['core_number'], $core->id);
            }
            $core->update([...$attributes, 'updated_by' => $user->id]);

            return $core->fresh();
        });
    }

    public function delete(FiberCore $core): void
    {
        DB::transaction(function () use ($core) {
            $this->lockedSegment($core->fiber_segment_id);
            $core->delete();
        });
    }

    public function generate(FiberSegment $segment, int $startCoreNumber, int $count, User $user): array
    {
        return DB::transaction(function () use ($segment, $startCoreNumber, $count, $user) {
            $segment = $this->lockedSegment($segment->id);
            $numbers = range($startCoreNumber, $startCoreNumber + $count - 1);
            $existing = FiberCore::query()
                ->where('fiber_segment_id', $segment->id)
                ->whereIn('core_number', $numbers)
                ->pluck('core_number')
                ->all();
            $existingNumbers = array_flip($existing);
            $created = [];

            foreach ($numbers as $number) {
                if (isset($existingNumbers[$number])) {
                    continue;
                }

                $created[] = FiberCore::create([
                    'fiber_segment_id' => $segment->id,
                    'company_id' => $segment->company_id,
                    'core_number' => $number,
                    'status' => 'available',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            return [
                'cores' => $created,
                'created_count' => count($created),
                'skipped_existing_count' => count($numbers) - count($created),
            ];
        });
    }

    protected function lockedSegment(int $segmentId): FiberSegment
    {
        return FiberSegment::query()->lockForUpdate()->findOrFail($segmentId);
    }

    protected function ensureNumberIsAvailable(int $segmentId, int $coreNumber, ?int $ignoreId = null): void
    {
        $query = FiberCore::where('fiber_segment_id', $segmentId)->where('core_number', $coreNumber);
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'core_number' => 'The core number has already been used in this fiber segment.',
            ]);
        }
    }
}
