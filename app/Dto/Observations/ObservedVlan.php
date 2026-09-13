<?php

namespace App\Dto\Observations;

/**
 * Provider-neutral observed VLAN record. Never used to author the
 * authoritative `vlans` catalog — only for observation + reconciliation.
 */
final class ObservedVlan
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $vid,
        public readonly ?string $name = null,
        public readonly ?string $vlanType = null,
        public readonly ?string $externalType = null,
        public readonly string|int|null $externalId = null,
        public readonly array $metadata = [],
    ) {}
}
