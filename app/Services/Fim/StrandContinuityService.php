<?php

namespace App\Services\Fim;

use App\Models\FiberCore;
use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\NetworkConnectionPoint;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Models\PassiveOpticalPort;
use App\Models\PhysicalConnection;
use App\Models\User;
use App\Services\ManagementScopeService;

class StrandContinuityService
{
    private const MAX_DEPTH = 50;

    /**
     * Detect splice+attachment conflict on a termination.
     *
     * Returns the terminal array if both edges exist, null otherwise.
     * Extracted for unit testing without database state.
     */
    public static function detectConflict(
        ?PhysicalConnection $splice,
        ?FiberTerminationPortAttachment $attachment,
        User $user,
    ): ?array {
        if ($splice === null || $attachment === null) {
            return null;
        }

        $spliceInScope = ManagementScopeService::isInScope($user, $splice);
        $attachmentInScope = ManagementScopeService::isInScope($user, $attachment);

        $conflictData = [];
        if ($spliceInScope) {
            $conflictData['splice'] = [
                'id' => $splice->id,
                'connection_type' => $splice->connection_type,
            ];
        }
        if ($attachmentInScope) {
            $conflictData['attachment'] = [
                'id' => $attachment->id,
                'passive_optical_port_id' => $attachment->passive_optical_port_id,
            ];
        }

        return ['type' => 'topology_conflict', 'id' => null, 'data' => $conflictData];
    }

    public function traceFromCore(
        FiberCore $startCore,
        User $user,
        string $mode = 'physical-strand',
        int $maxDepth = 20,
    ): StrandPath {
        $maxDepth = min($maxDepth, self::MAX_DEPTH);

        $startCableId = $startCore->fiberSegment->fiber_cable_id;
        $visitedCoreIds = ["FiberCore:{$startCore->id}"];
        $visitedEdgeIds = [];

        $coresA = [];
        $connectionsA = [];
        $terminalA = null;
        $depthA = 0;
        $truncatedA = false;
        $cycleDetected = false;
        $cableBoundaryCrossed = false;

        $this->extendDirection(
            startCore: $startCore,
            exitEnd: 'A',
            user: $user,
            mode: $mode,
            startCableId: $startCableId,
            maxDepth: $maxDepth,
            visitedCoreIds: $visitedCoreIds,
            visitedEdgeIds: $visitedEdgeIds,
            cores: $coresA,
            connections: $connectionsA,
            terminal: $terminalA,
            depth: $depthA,
            truncated: $truncatedA,
            cycleDetected: $cycleDetected,
            cableBoundaryCrossed: $cableBoundaryCrossed,
        );

        $coresA = array_reverse($coresA);
        $connectionsA = array_reverse($connectionsA);

        $coresB = [];
        $connectionsB = [];
        $terminalB = null;
        $depthB = 0;
        $truncatedB = false;

        $this->extendDirection(
            startCore: $startCore,
            exitEnd: 'B',
            user: $user,
            mode: $mode,
            startCableId: $startCableId,
            maxDepth: $maxDepth,
            visitedCoreIds: $visitedCoreIds,
            visitedEdgeIds: $visitedEdgeIds,
            cores: $coresB,
            connections: $connectionsB,
            terminal: $terminalB,
            depth: $depthB,
            truncated: $truncatedB,
            cycleDetected: $cycleDetected,
            cableBoundaryCrossed: $cableBoundaryCrossed,
        );

        $startCoreData = $this->hydrateCore($startCore, $user);

        $cores = array_merge($coresA, [$startCoreData], $coresB);
        $connections = array_merge($connectionsA, $connectionsB);

        return new StrandPath(
            fiberCoreId: $startCore->id,
            mode: $mode,
            cores: $cores,
            connections: $connections,
            terminalA: $terminalA,
            terminalB: $terminalB,
            depth: $depthA + $depthB,
            truncated: $truncatedA || $truncatedB,
            cycleDetected: $cycleDetected,
            cableBoundaryCrossed: $cableBoundaryCrossed,
        );
    }

    /**
     * @param  array<int, string>  $visitedCoreIds
     * @param  array<int, string>  $visitedEdgeIds
     * @param  array<int, array<string, mixed>>  $cores
     * @param  array<int, array<string, mixed>>  $connections
     */
    private function extendDirection(
        FiberCore $startCore,
        string $exitEnd,
        User $user,
        string $mode,
        int $startCableId,
        int $maxDepth,
        array &$visitedCoreIds,
        array &$visitedEdgeIds,
        array &$cores,
        array &$connections,
        ?array &$terminal,
        int &$depth,
        bool &$truncated,
        bool &$cycleDetected,
        bool &$cableBoundaryCrossed,
    ): void {
        $currentCore = $startCore;
        $currentExitEnd = $exitEnd;
        $depth = 0;

        while ($currentCore !== null) {
            $termination = FiberTermination::where('fiber_core_id', $currentCore->id)
                ->where('segment_end', $currentExitEnd)
                ->whereNull('deleted_at')
                ->first();

            if ($termination === null) {
                $terminal = ['type' => 'no_termination', 'id' => null, 'data' => null];

                return;
            }

            if (! ManagementScopeService::isInScope($user, $termination)) {
                $terminal = ['type' => 'scope_boundary', 'id' => null, 'data' => null];

                return;
            }

            $splice = PhysicalConnection::whereNull('deleted_at')
                ->where(function ($q) use ($termination) {
                    $q->where('termination_a_id', $termination->id)
                        ->orWhere('termination_b_id', $termination->id);
                })
                ->first();

            $attachment = FiberTerminationPortAttachment::whereNull('deleted_at')
                ->where('fiber_termination_id', $termination->id)
                ->first();

            if ($splice !== null && $attachment !== null) {
                $terminal = self::detectConflict($splice, $attachment, $user);

                return;
            }

            $networkAttachment = NetworkPortFiberTerminationAttachment::where('fiber_termination_id', $termination->id)->first();
            if ($networkAttachment !== null) {
                if ($splice !== null || $attachment !== null) {
                    $terminal = ['type' => 'topology_conflict', 'id' => null, 'data' => null];
                } elseif (! $user->can('view', $networkAttachment)) {
                    $terminal = ['type' => 'scope_boundary', 'id' => null, 'data' => null];
                } else {
                    $terminal = ['type' => 'active_network_port', 'id' => $networkAttachment->network_port_id, 'data' => null];
                }

                return;
            }

            if ($splice === null && $attachment === null) {
                $terminal = ['type' => 'no_splice', 'id' => null, 'data' => null];

                return;
            }

            if ($splice === null && $attachment !== null) {
                if (! ManagementScopeService::isInScope($user, $attachment)) {
                    $terminal = ['type' => 'scope_boundary', 'id' => null, 'data' => null];

                    return;
                }

                $port = $attachment->passiveOpticalPort;
                if (! ManagementScopeService::isInScope($user, $port)) {
                    $terminal = ['type' => 'scope_boundary', 'id' => null, 'data' => null];

                    return;
                }

                $terminal = [
                    'type' => 'passive_optical_port',
                    'id' => $port->id,
                    'data' => $this->hydratePort($port, $user),
                ];

                return;
            }

            if (! ManagementScopeService::isInScope($user, $splice)) {
                $terminal = ['type' => 'scope_boundary', 'id' => null, 'data' => null];

                return;
            }

            $connectedTerminationId = ($splice->termination_a_id === $termination->id)
                ? $splice->termination_b_id
                : $splice->termination_a_id;

            $connectedTermination = FiberTermination::find($connectedTerminationId);

            if ($connectedTermination === null || ! ManagementScopeService::isInScope($user, $connectedTermination)) {
                $terminal = ['type' => 'scope_boundary', 'id' => null, 'data' => null];

                return;
            }

            $nextCore = $connectedTermination->fiberCore;

            $nextCoreKey = "FiberCore:{$nextCore->id}";
            if (in_array($nextCoreKey, $visitedCoreIds, true)) {
                $cycleDetected = true;

                return;
            }

            if (! ManagementScopeService::isInScope($user, $nextCore)) {
                $terminal = ['type' => 'scope_boundary', 'id' => null, 'data' => null];

                return;
            }

            if ($mode === 'same-cable-strand' && $nextCore->fiberSegment->fiber_cable_id !== $startCableId) {
                $cableBoundaryCrossed = true;
                $terminal = ['type' => 'cable_boundary', 'id' => null, 'data' => null];

                return;
            }

            $visitedCoreIds[] = $nextCoreKey;

            $connections[] = $this->hydrateConnection($splice, $termination->id, $connectedTerminationId, $user);

            $cores[] = $this->hydrateCore($nextCore, $user);

            $edgeKey = "PhysicalConnection:{$splice->id}";
            if (! in_array($edgeKey, $visitedEdgeIds, true)) {
                $visitedEdgeIds[] = $edgeKey;
            }

            $currentCore = $nextCore;
            $currentExitEnd = ($connectedTermination->segment_end === 'A') ? 'B' : 'A';
            $depth++;

            if ($depth >= $maxDepth) {
                $nextTermination = FiberTermination::where('fiber_core_id', $currentCore->id)
                    ->where('segment_end', $currentExitEnd)
                    ->whereNull('deleted_at')
                    ->first();

                if ($nextTermination !== null) {
                    $hasContinuation = PhysicalConnection::whereNull('deleted_at')
                        ->where(function ($q) use ($nextTermination) {
                            $q->where('termination_a_id', $nextTermination->id)
                                ->orWhere('termination_b_id', $nextTermination->id);
                        })
                        ->exists();

                    if ($hasContinuation) {
                        $truncated = true;
                    }
                }

                return;
            }
        }
    }

    private function hydrateCore(FiberCore $core, User $user): array
    {
        $data = $core->only(['core_number', 'status', 'metadata']);
        $data['fiber_core_id'] = $core->id;

        if (ManagementScopeService::isInScope($user, $core->fiberSegment)) {
            $data['fiber_segment'] = $core->fiberSegment->only([
                'id', 'sequence', 'length_meters', 'endpoint_a_id', 'endpoint_b_id', 'status',
            ]);

            if (ManagementScopeService::isInScope($user, $core->fiberSegment->fiberCable)) {
                $data['fiber_cable'] = $core->fiberSegment->fiberCable->only([
                    'id', 'name', 'cable_type', 'fiber_count', 'status',
                ]);
            }
        }

        $ncp = $this->resolveTerminationNcp($core);
        if ($ncp !== null && ManagementScopeService::isInScope($user, $ncp)) {
            $data['network_connection_point'] = $ncp->only(['id', 'point_type', 'name']);
            if ($ncp->site !== null) {
                $data['site'] = $ncp->site->only(['id', 'name']);
            }
        }

        return $data;
    }

    private function resolveTerminationNcp(FiberCore $core): ?NetworkConnectionPoint
    {
        $termination = FiberTermination::where('fiber_core_id', $core->id)
            ->whereNull('deleted_at')
            ->first();

        return $termination?->networkConnectionPoint;
    }

    private function hydrateConnection(
        PhysicalConnection $splice,
        int $fromTerminationId,
        int $toTerminationId,
        User $user,
    ): array {
        $data = $splice->only(['id', 'connection_type', 'metadata']);
        $data['from_termination_id'] = $fromTerminationId;
        $data['to_termination_id'] = $toTerminationId;

        $ncp = $splice->terminationA->networkConnectionPoint;
        if ($ncp !== null && ManagementScopeService::isInScope($user, $ncp)) {
            $data['network_connection_point'] = $ncp->only(['id', 'point_type', 'name']);
        }

        return $data;
    }

    private function hydratePort(PassiveOpticalPort $port, User $user): array
    {
        $data = $port->only(['id', 'port_number', 'port_role', 'status', 'metadata']);

        $ncp = $port->networkConnectionPoint;
        if ($ncp !== null && ManagementScopeService::isInScope($user, $ncp)) {
            $data['network_connection_point'] = $ncp->only(['id', 'point_type', 'name']);
            if ($ncp->site !== null) {
                $data['site'] = $ncp->site->only(['id', 'name']);
            }
        }

        return $data;
    }
}
