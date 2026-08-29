<?php

namespace App\Contracts;

use App\Models\Integration;

interface IntegrationProviderInterface
{
    /** Unique provider identifier */
    public function identity(): string;

    /** Human-readable provider name */
    public function displayName(): string;

    /** Declared capabilities */
    public function capabilities(): array;

    /** Validate integration configuration before save */
    public function validateConfiguration(array $config): array;

    /** Test connectivity to external system */
    public function testConnection(Integration $integration): array;

    /** Perform health check against external system */
    public function healthCheck(Integration $integration): array;

    /** Execute synchronization */
    public function synchronize(Integration $integration, string $operation = 'full'): array;
}
