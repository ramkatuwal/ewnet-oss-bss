<?php

namespace App\Dto\Observations;

/**
 * Provider-neutral observed IP address record bound to a device interface.
 */
final class ObservedAddress
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $ip,
        public readonly ?int $prefixLength = null,
        public readonly ?int $family = null,
        public readonly bool $isPrimary = false,
        public readonly bool $isManagement = false,
        public readonly ?string $externalType = null,
        public readonly string|int|null $externalId = null,
        public readonly array $metadata = [],
    ) {}
}
