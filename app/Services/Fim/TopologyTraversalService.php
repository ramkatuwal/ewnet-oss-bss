<?php

namespace App\Services\Fim;

use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\PassiveOpticalPort;
use App\Models\PhysicalConnection;
use App\Models\SplitterBranch;

class TopologyTraversalService
{
    private const MAX_DEPTH = 50;

    private const DIRECTION_BOTH = 'both';

    private const DIRECTION_DOWNSTREAM = 'downstream';

    private const DIRECTION_TRACEBACK = 'trace-back';

    public function traverseFromTermination(
        FiberTermination $start,
        string $direction = self::DIRECTION_BOTH,
        int $maxDepth = 20,
        bool $includeContainment = true,
    ): TopologicalPath {
        $maxDepth = min($maxDepth, self::MAX_DEPTH);
        $visitedIds = new \SplObjectStorage;
        $visitedEdgeIds = [];
        $nodes = [];
        $edges = [];

        $nodes[] = ['type' => 'FiberTermination', 'id' => $start->id, 'data' => $this->hydrateTermination($start, $includeContainment)];
        $visitedIds->attach($start);

        $frontierTerminations = [$start->id];
        $frontierPorts = [];
        $depth = 0;
        $truncated = false;

        while (($frontierTerminations !== [] || $frontierPorts !== []) && $depth < $maxDepth) {
            $nextTerminations = [];
            $nextPorts = [];

            if ($direction === self::DIRECTION_BOTH || $direction === self::DIRECTION_TRACEBACK) {
                $this->expandTerminationsViaSplice($frontierTerminations, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextTerminations);
            }

            if ($frontierTerminations !== []) {
                $this->expandTerminationsViaAttachment($frontierTerminations, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextPorts);
            }

            if ($frontierPorts !== []) {
                $this->expandPortsViaAttachment($frontierPorts, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextTerminations);
                $this->expandPortsViaSplitter($frontierPorts, $direction, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextPorts);
            }

            $frontierTerminations = $nextTerminations;
            $frontierPorts = $nextPorts;

            if ($frontierTerminations !== [] || $frontierPorts !== []) {
                $depth++;
            }

            if ($depth >= $maxDepth && ($frontierTerminations !== [] || $frontierPorts !== [])) {
                $truncated = true;
            }
        }

        return new TopologicalPath(
            nodes: $nodes,
            edges: $edges,
            startNodeType: 'FiberTermination',
            startNodeId: $start->id,
            depth: $depth,
            truncated: $truncated,
        );
    }

    public function traverseFromPort(
        PassiveOpticalPort $start,
        string $direction = self::DIRECTION_BOTH,
        int $maxDepth = 20,
        bool $includeContainment = true,
    ): TopologicalPath {
        $maxDepth = min($maxDepth, self::MAX_DEPTH);
        $visitedIds = new \SplObjectStorage;
        $visitedEdgeIds = [];
        $nodes = [];
        $edges = [];

        $nodes[] = ['type' => 'PassiveOpticalPort', 'id' => $start->id, 'data' => $this->hydratePort($start, $includeContainment)];
        $visitedIds->attach($start);

        $frontierPorts = [$start->id];
        $frontierTerminations = [];
        $depth = 0;
        $truncated = false;

        while (($frontierPorts !== [] || $frontierTerminations !== []) && $depth < $maxDepth) {
            $nextPorts = [];
            $nextTerminations = [];

            $this->expandPortsViaAttachment($frontierPorts, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextTerminations);
            $this->expandPortsViaSplitter($frontierPorts, $direction, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextPorts);

            if ($frontierTerminations !== []) {
                if ($direction === self::DIRECTION_BOTH || $direction === self::DIRECTION_TRACEBACK) {
                    $this->expandTerminationsViaSplice($frontierTerminations, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextTerminations);
                }
                $this->expandTerminationsViaAttachment($frontierTerminations, $visitedIds, $visitedEdgeIds, $nodes, $edges, $nextPorts);
            }

            $frontierPorts = $nextPorts;
            $frontierTerminations = $nextTerminations;

            if ($frontierPorts !== [] || $frontierTerminations !== []) {
                $depth++;
            }

            if ($depth >= $maxDepth && ($frontierPorts !== [] || $frontierTerminations !== [])) {
                $truncated = true;
            }
        }

        return new TopologicalPath(
            nodes: $nodes,
            edges: $edges,
            startNodeType: 'PassiveOpticalPort',
            startNodeId: $start->id,
            depth: $depth,
            truncated: $truncated,
        );
    }

    private function expandTerminationsViaSplice(
        array $terminationIds,
        \SplObjectStorage $visitedIds,
        array &$visitedEdgeIds,
        array &$nodes,
        array &$edges,
        array &$nextTerminations,
    ): void {
        if ($terminationIds === []) {
            return;
        }

        $connections = PhysicalConnection::whereNull('deleted_at')
            ->where(function ($q) use ($terminationIds) {
                $q->whereIn('termination_a_id', $terminationIds)
                    ->orWhereIn('termination_b_id', $terminationIds);
            })
            ->get();

        foreach ($connections as $conn) {
            $edgeKey = "PhysicalConnection:{$conn->id}";
            if (in_array($edgeKey, $visitedEdgeIds)) {
                continue;
            }
            $visitedEdgeIds[] = $edgeKey;

            $aId = $conn->termination_a_id;
            $bId = $conn->termination_b_id;

            $edges[] = [
                'type' => 'PhysicalConnection',
                'id' => $conn->id,
                'from' => "FiberTermination:{$aId}",
                'to' => "FiberTermination:{$bId}",
                'data' => $conn->only(['id', 'connection_type', 'company_id', 'metadata', 'created_at']),
            ];

            $adjacentIds = array_unique(array_diff([$aId, $bId], $terminationIds));

            foreach ($adjacentIds as $adjId) {
                $existingNodeIds = array_column(array_filter($nodes, fn ($n) => $n['type'] === 'FiberTermination'), 'id');
                if (in_array($adjId, $existingNodeIds)) {
                    continue;
                }

                $term = FiberTermination::find($adjId);
                if ($term && ! $visitedIds->contains($term)) {
                    $visitedIds->attach($term);
                    $nodes[] = ['type' => 'FiberTermination', 'id' => $term->id, 'data' => $this->hydrateTermination($term, true)];
                    $nextTerminations[] = $term->id;
                }
            }
        }
    }

    private function expandTerminationsViaAttachment(
        array $terminationIds,
        \SplObjectStorage $visitedIds,
        array &$visitedEdgeIds,
        array &$nodes,
        array &$edges,
        array &$nextPorts,
    ): void {
        if ($terminationIds === []) {
            return;
        }

        $attachments = FiberTerminationPortAttachment::whereNull('deleted_at')
            ->whereIn('fiber_termination_id', $terminationIds)
            ->get();

        foreach ($attachments as $att) {
            $edgeKey = "FiberTerminationPortAttachment:{$att->id}";
            if (in_array($edgeKey, $visitedEdgeIds)) {
                continue;
            }
            $visitedEdgeIds[] = $edgeKey;

            $edges[] = [
                'type' => 'FiberTerminationPortAttachment',
                'id' => $att->id,
                'from' => "FiberTermination:{$att->fiber_termination_id}",
                'to' => "PassiveOpticalPort:{$att->passive_optical_port_id}",
                'data' => $att->only(['id', 'company_id', 'metadata', 'created_at']),
            ];

            $existingPortIds = array_column(array_filter($nodes, fn ($n) => $n['type'] === 'PassiveOpticalPort'), 'id');
            if (in_array($att->passive_optical_port_id, $existingPortIds)) {
                continue;
            }

            $port = PassiveOpticalPort::find($att->passive_optical_port_id);
            if ($port && ! $visitedIds->contains($port)) {
                $visitedIds->attach($port);
                $nodes[] = ['type' => 'PassiveOpticalPort', 'id' => $port->id, 'data' => $this->hydratePort($port, true)];
                $nextPorts[] = $port->id;
            }
        }
    }

    private function expandPortsViaAttachment(
        array $portIds,
        \SplObjectStorage $visitedIds,
        array &$visitedEdgeIds,
        array &$nodes,
        array &$edges,
        array &$nextTerminations,
    ): void {
        if ($portIds === []) {
            return;
        }

        $attachments = FiberTerminationPortAttachment::whereNull('deleted_at')
            ->whereIn('passive_optical_port_id', $portIds)
            ->get();

        foreach ($attachments as $att) {
            $edgeKey = "FiberTerminationPortAttachment:{$att->id}";
            if (in_array($edgeKey, $visitedEdgeIds)) {
                continue;
            }
            $visitedEdgeIds[] = $edgeKey;

            $edges[] = [
                'type' => 'FiberTerminationPortAttachment',
                'id' => $att->id,
                'from' => "PassiveOpticalPort:{$att->passive_optical_port_id}",
                'to' => "FiberTermination:{$att->fiber_termination_id}",
                'data' => $att->only(['id', 'company_id', 'metadata', 'created_at']),
            ];

            $existingTermIds = array_column(array_filter($nodes, fn ($n) => $n['type'] === 'FiberTermination'), 'id');
            if (in_array($att->fiber_termination_id, $existingTermIds)) {
                continue;
            }

            $term = FiberTermination::find($att->fiber_termination_id);
            if ($term && ! $visitedIds->contains($term)) {
                $visitedIds->attach($term);
                $nodes[] = ['type' => 'FiberTermination', 'id' => $term->id, 'data' => $this->hydrateTermination($term, true)];
                $nextTerminations[] = $term->id;
            }
        }
    }

    private function expandPortsViaSplitter(
        array $portIds,
        string $direction,
        \SplObjectStorage $visitedIds,
        array &$visitedEdgeIds,
        array &$nodes,
        array &$edges,
        array &$nextPorts,
    ): void {
        if ($portIds === []) {
            return;
        }

        if ($direction === self::DIRECTION_DOWNSTREAM || $direction === self::DIRECTION_BOTH) {
            $branchesOut = SplitterBranch::whereNull('deleted_at')
                ->whereIn('input_port_id', $portIds)
                ->get();

            foreach ($branchesOut as $branch) {
                $edgeKey = "SplitterBranch:{$branch->id}";
                if (in_array($edgeKey, $visitedEdgeIds)) {
                    continue;
                }
                $visitedEdgeIds[] = $edgeKey;

                $edges[] = [
                    'type' => 'SplitterBranch',
                    'id' => $branch->id,
                    'from' => "PassiveOpticalPort:{$branch->input_port_id}",
                    'to' => "PassiveOpticalPort:{$branch->output_port_id}",
                    'data' => $branch->only(['id', 'splitter_profile_id', 'company_id', 'metadata', 'created_at']),
                ];

                $existingPortIds = array_column(array_filter($nodes, fn ($n) => $n['type'] === 'PassiveOpticalPort'), 'id');
                if (in_array($branch->output_port_id, $existingPortIds)) {
                    continue;
                }

                $port = PassiveOpticalPort::find($branch->output_port_id);
                if ($port && ! $visitedIds->contains($port)) {
                    $visitedIds->attach($port);
                    $nodes[] = ['type' => 'PassiveOpticalPort', 'id' => $port->id, 'data' => $this->hydratePort($port, true)];
                    $nextPorts[] = $port->id;
                }
            }
        }

        if ($direction === self::DIRECTION_TRACEBACK || $direction === self::DIRECTION_BOTH) {
            $branchesIn = SplitterBranch::whereNull('deleted_at')
                ->whereIn('output_port_id', $portIds)
                ->get();

            foreach ($branchesIn as $branch) {
                $edgeKey = "SplitterBranch:{$branch->id}";
                if (in_array($edgeKey, $visitedEdgeIds)) {
                    continue;
                }
                $visitedEdgeIds[] = $edgeKey;

                $edges[] = [
                    'type' => 'SplitterBranch',
                    'id' => $branch->id,
                    'from' => "PassiveOpticalPort:{$branch->output_port_id}",
                    'to' => "PassiveOpticalPort:{$branch->input_port_id}",
                    'data' => $branch->only(['id', 'splitter_profile_id', 'company_id', 'metadata', 'created_at']),
                ];

                $existingPortIds = array_column(array_filter($nodes, fn ($n) => $n['type'] === 'PassiveOpticalPort'), 'id');
                if (in_array($branch->input_port_id, $existingPortIds)) {
                    continue;
                }

                $port = PassiveOpticalPort::find($branch->input_port_id);
                if ($port && ! $visitedIds->contains($port)) {
                    $visitedIds->attach($port);
                    $nodes[] = ['type' => 'PassiveOpticalPort', 'id' => $port->id, 'data' => $this->hydratePort($port, true)];
                    $nextPorts[] = $port->id;
                }
            }
        }
    }

    private function hydrateTermination(FiberTermination $t, bool $includeContainment): array
    {
        $data = $t->only(['id', 'fiber_core_id', 'company_id', 'network_connection_point_id', 'segment_end', 'metadata', 'created_at']);

        if ($includeContainment) {
            $data['fiber_core'] = $t->fiberCore->only(['id', 'fiber_segment_id', 'core_number', 'status', 'metadata']);
            $data['fiber_segment'] = $t->fiberCore->fiberSegment->only(['id', 'fiber_cable_id', 'endpoint_a_id', 'endpoint_b_id', 'sequence', 'length_meters', 'status']);
            $data['fiber_cable'] = $t->fiberCore->fiberSegment->fiberCable->only(['id', 'name', 'cable_type', 'fiber_count', 'status']);
            $data['network_connection_point'] = $t->networkConnectionPoint->only(['id', 'point_type', 'name']);
            if ($t->networkConnectionPoint->site) {
                $data['site'] = $t->networkConnectionPoint->site->only(['id', 'name']);
            }
        }

        return $data;
    }

    private function hydratePort(PassiveOpticalPort $p, bool $includeContainment): array
    {
        $data = $p->only(['id', 'asset_id', 'company_id', 'network_connection_point_id', 'port_number', 'connector_type', 'port_role', 'metadata', 'created_at']);

        if ($includeContainment) {
            $data['asset'] = $p->asset->only(['id', 'name', 'serial_number', 'status']);
            $data['network_connection_point'] = $p->networkConnectionPoint->only(['id', 'point_type', 'name']);
            if ($p->networkConnectionPoint->site) {
                $data['site'] = $p->networkConnectionPoint->site->only(['id', 'name']);
            }
        }

        return $data;
    }
}
