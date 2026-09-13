<?php

namespace App\Dto\Observations;

/**
 * Provider-neutral observed interface record.
 *
 * This is a read-only observation DTO. It is NEVER used to author
 * authoritative network intent; it only feeds the observation store.
 */
final class ObservedInterface
{
    /**
     * @param  array<int, ObservedAddress>  $addresses
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $displayName = null,
        public readonly ?string $description = null,
        public readonly ?string $type = null,
        public readonly ?int $speed = null,
        public readonly ?string $adminStatus = null,
        public readonly ?string $operStatus = null,
        public readonly ?string $macAddress = null,
        public readonly ?int $mtu = null,
        public readonly ?int $accessVlan = null,
        public readonly string|int|null $externalId = null,
        public readonly ?string $externalType = null,
        public readonly string|int|null $ifIndex = null,
        public readonly array $addresses = [],
        public readonly bool $isManagement = false,
        public readonly array $metadata = [],
    ) {}
}
