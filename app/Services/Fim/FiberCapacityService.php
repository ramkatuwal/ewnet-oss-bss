<?php

namespace App\Services\Fim;

use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\PhysicalConnection;

class FiberCapacityService
{
    /**
     * Compute segment-level capacity metrics.
     *
     * @return array<string, mixed>
     */
    public function segmentCapacity(FiberSegment $segment): array
    {
        $cable = $segment->fiberCable;
        $nominalCapacity = $cable->fiber_count;

        $coreIds = FiberCore::where('fiber_segment_id', $segment->id)
            ->whereNull('deleted_at')
            ->pluck('id');

        $inventoriedCount = $coreIds->count();

        $dataInconsistency = $inventoriedCount > $nominalCapacity;
        $unaccounted = $dataInconsistency ? 0 : $nominalCapacity - $inventoriedCount;
        $inventoryComplete = $inventoriedCount === $nominalCapacity;

        if ($inventoriedCount === 0) {
            return $this->emptySegmentCapacity($segment, $nominalCapacity, $inventoryComplete, $unaccounted, $dataInconsistency);
        }

        $terminationCounts = $this->computeTerminationCounts($coreIds->all());
        $connectivityCounts = $this->computeConnectivityCounts($coreIds->all());

        return [
            'fiber_segment_id' => $segment->id,
            'nominal_capacity' => $nominalCapacity,
            'inventoried_count' => $inventoriedCount,
            'unaccounted' => $unaccounted,
            'inventory_complete' => $inventoryComplete,
            'data_inconsistency' => $dataInconsistency,
            'termination_count' => $terminationCounts['termination_count'],
            'fully_terminated_core_count' => $terminationCounts['fully_terminated_core_count'],
            'partially_terminated_core_count' => $terminationCounts['partially_terminated_core_count'],
            'unterminated_core_count' => $terminationCounts['unterminated_core_count'],
            'connected_termination_count' => $connectivityCounts['connected_termination_count'],
            'no_external_connectivity_core_count' => $connectivityCounts['no_external_connectivity_core_count'],
            'partially_connected_core_count' => $connectivityCounts['partially_connected_core_count'],
            'fully_connected_core_count' => $connectivityCounts['fully_connected_core_count'],
        ];
    }

    /**
     * Compute cable-level capacity as a roll-up of segment metrics.
     *
     * @return array<string, mixed>
     */
    public function cableCapacity(FiberCable $cable): array
    {
        $segments = FiberSegment::where('fiber_cable_id', $cable->id)
            ->whereNull('deleted_at')
            ->orderBy('sequence')
            ->get();

        $segmentMetrics = [];
        $fullyInventoriedCount = 0;
        $partiallyInventoriedCount = 0;

        foreach ($segments as $segment) {
            $metrics = $this->segmentCapacity($segment);
            $segmentMetrics[] = $metrics;

            if ($metrics['inventory_complete']) {
                $fullyInventoriedCount++;
            } else {
                $partiallyInventoriedCount++;
            }
        }

        return [
            'fiber_cable_id' => $cable->id,
            'fiber_count' => $cable->fiber_count,
            'segment_count' => $segments->count(),
            'segments' => $segmentMetrics,
            'fully_inventoried_segment_count' => $fullyInventoriedCount,
            'partially_inventoried_segment_count' => $partiallyInventoriedCount,
            'inventory_complete_across_all_segments' => $segments->isNotEmpty()
                && $fullyInventoriedCount === $segments->count(),
        ];
    }

    /**
     * Compute termination inventory counts for a set of core IDs.
     *
     * @param  int[]  $coreIds
     * @return array<string, int>
     */
    private function computeTerminationCounts(array $coreIds): array
    {
        $terminations = FiberTermination::whereNull('deleted_at')
            ->whereIn('fiber_core_id', $coreIds)
            ->select('fiber_core_id', 'segment_end')
            ->get();

        $perCore = $terminations->groupBy('fiber_core_id');

        $fullyTerminated = 0;
        $partiallyTerminated = 0;
        $unterminated = 0;
        $totalTerminations = 0;

        foreach ($perCore as $coreId => $terms) {
            $count = $terms->count();
            $totalTerminations += $count;

            if ($count === 2) {
                $fullyTerminated++;
            } else {
                $partiallyTerminated++;
            }
        }

        $unterminated = count($coreIds) - $perCore->count();

        return [
            'termination_count' => $totalTerminations,
            'fully_terminated_core_count' => $fullyTerminated,
            'partially_terminated_core_count' => $partiallyTerminated,
            'unterminated_core_count' => $unterminated,
        ];
    }

    /**
     * Compute external connectivity counts for a set of core IDs.
     *
     * @param  int[]  $coreIds
     * @return array<string, int>
     */
    private function computeConnectivityCounts(array $coreIds): array
    {
        $terminations = FiberTermination::whereNull('deleted_at')
            ->whereIn('fiber_core_id', $coreIds)
            ->select('id', 'fiber_core_id')
            ->get();

        if ($terminations->isEmpty()) {
            return [
                'connected_termination_count' => 0,
                'no_external_connectivity_core_count' => count($coreIds),
                'partially_connected_core_count' => 0,
                'fully_connected_core_count' => 0,
            ];
        }

        $terminationIds = $terminations->pluck('id')->all();

        $connectedTerminationIds = $this->findConnectedTerminationIds($terminationIds);

        $connectedMap = [];
        foreach ($connectedTerminationIds as $connTermId) {
            $connectedMap[$connTermId] = true;
        }

        $perCoreConnected = [];
        foreach ($terminations as $term) {
            $coreId = $term->fiber_core_id;
            if (! isset($perCoreConnected[$coreId])) {
                $perCoreConnected[$coreId] = 0;
            }
            if (isset($connectedMap[$term->id])) {
                $perCoreConnected[$coreId]++;
            }
        }

        $connectedTerminationCount = 0;
        $noExternal = 0;
        $partial = 0;
        $full = 0;

        foreach ($coreIds as $coreId) {
            $connected = $perCoreConnected[$coreId] ?? 0;
            $connectedTerminationCount += $connected;

            $coreTerminations = $terminations->where('fiber_core_id', $coreId)->count();

            if ($coreTerminations === 0) {
                $noExternal++;
            } elseif ($connected === 0) {
                $noExternal++;
            } elseif ($connected < $coreTerminations) {
                $partial++;
            } else {
                $full++;
            }
        }

        return [
            'connected_termination_count' => $connectedTerminationCount,
            'no_external_connectivity_core_count' => $noExternal,
            'partially_connected_core_count' => $partial,
            'fully_connected_core_count' => $full,
        ];
    }

    /**
     * Find termination IDs that have at least one live PhysicalConnection
     * or FiberTerminationPortAttachment.
     *
     * @param  int[]  $terminationIds
     * @return int[]
     */
    private function findConnectedTerminationIds(array $terminationIds): array
    {
        $spliceConnected = PhysicalConnection::whereNull('deleted_at')
            ->where(function ($q) use ($terminationIds) {
                $q->whereIn('termination_a_id', $terminationIds)
                    ->orWhereIn('termination_b_id', $terminationIds);
            })
            ->pluck('termination_a_id')
            ->merge(PhysicalConnection::whereNull('deleted_at')
                ->where(function ($q) use ($terminationIds) {
                    $q->whereIn('termination_a_id', $terminationIds)
                        ->orWhereIn('termination_b_id', $terminationIds);
                })
                ->pluck('termination_b_id'))
            ->unique()
            ->filter(fn ($id) => in_array($id, $terminationIds))
            ->values()
            ->all();

        $portConnected = FiberTerminationPortAttachment::whereNull('deleted_at')
            ->whereIn('fiber_termination_id', $terminationIds)
            ->pluck('fiber_termination_id')
            ->all();

        return array_unique(array_merge($spliceConnected, $portConnected));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySegmentCapacity(
        FiberSegment $segment,
        int $nominalCapacity,
        bool $inventoryComplete,
        int $unaccounted,
        bool $dataInconsistency,
    ): array {
        return [
            'fiber_segment_id' => $segment->id,
            'nominal_capacity' => $nominalCapacity,
            'inventoried_count' => 0,
            'unaccounted' => $unaccounted,
            'inventory_complete' => $inventoryComplete,
            'data_inconsistency' => $dataInconsistency,
            'termination_count' => 0,
            'fully_terminated_core_count' => 0,
            'partially_terminated_core_count' => 0,
            'unterminated_core_count' => 0,
            'connected_termination_count' => 0,
            'no_external_connectivity_core_count' => 0,
            'partially_connected_core_count' => 0,
            'fully_connected_core_count' => 0,
        ];
    }
}
