<?php

namespace App\Services\Fim;

use Illuminate\Contracts\Support\Arrayable;

readonly class TopologicalPath implements Arrayable
{
    public function __construct(
        public array $nodes,
        public array $edges,
        public string $startNodeType,
        public int $startNodeId,
        public int $depth,
        public bool $truncated,
    ) {}

    public static function empty(string $nodeType, int $nodeId): self
    {
        return new self(
            nodes: [['type' => $nodeType, 'id' => $nodeId]],
            edges: [],
            startNodeType: $nodeType,
            startNodeId: $nodeId,
            depth: 0,
            truncated: false,
        );
    }

    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'start_node' => "{$this->startNodeType}:{$this->startNodeId}",
            'depth' => $this->depth,
            'truncated' => $this->truncated,
        ];
    }
}
