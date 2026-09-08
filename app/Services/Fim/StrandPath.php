<?php

namespace App\Services\Fim;

use Illuminate\Contracts\Support\Arrayable;

readonly class StrandPath implements Arrayable
{
    /**
     * @param  array<int, array<string, mixed>>  $cores
     * @param  array<int, array<string, mixed>>  $connections
     * @param  array{type: string, id: int|null, data: array<string, mixed>|null}|null  $terminalA
     * @param  array{type: string, id: int|null, data: array<string, mixed>|null}|null  $terminalB
     */
    public function __construct(
        public int $fiberCoreId,
        public string $mode,
        public array $cores,
        public array $connections,
        public ?array $terminalA,
        public ?array $terminalB,
        public int $depth,
        public bool $truncated,
        public bool $cycleDetected,
        public bool $cableBoundaryCrossed,
    ) {}

    public static function empty(int $fiberCoreId, string $mode): self
    {
        return new self(
            fiberCoreId: $fiberCoreId,
            mode: $mode,
            cores: [],
            connections: [],
            terminalA: null,
            terminalB: null,
            depth: 0,
            truncated: false,
            cycleDetected: false,
            cableBoundaryCrossed: false,
        );
    }

    public function toArray(): array
    {
        return [
            'fiber_core_id' => $this->fiberCoreId,
            'mode' => $this->mode,
            'cores' => $this->cores,
            'connections' => $this->connections,
            'terminal_a' => $this->terminalA,
            'terminal_b' => $this->terminalB,
            'depth' => $this->depth,
            'truncated' => $this->truncated,
            'cycle_detected' => $this->cycleDetected,
            'cable_boundary_crossed' => $this->cableBoundaryCrossed,
        ];
    }
}
