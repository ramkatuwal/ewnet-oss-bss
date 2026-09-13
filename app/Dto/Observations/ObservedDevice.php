<?php

namespace App\Dto\Observations;

/**
 * Provider observation set for one external device.
 */
final class ObservedDevice
{
    /**
     * @param  array<int, ObservedInterface>  $interfaces
     * @param  array<int, ObservedVlan>  $vlans
     */
    public function __construct(
        public readonly string|int $externalId,
        public readonly string $provider,
        public readonly array $interfaces = [],
        public readonly array $vlans = [],
    ) {}
}
