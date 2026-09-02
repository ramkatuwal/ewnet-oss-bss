<?php

namespace App\Contracts;

use App\Models\Integration;

interface IntegrationProviderInterface
{
    public function identity(): string;

    public function displayName(): string;

    public function capabilities(): array;

    public function validateConfiguration(array $config): array;

    public function testConnection(Integration $integration): array;

    public function healthCheck(Integration $integration): array;

    /**
     * Synchronize data between EWNET and the external provider.
     *
     * @param Integration $integration The integration to synchronize
     * @return array{processed:int, created:int, updated:int, unchanged:int, skipped:int, failed:int, status?:string}
     */
    public function synchronize(Integration $integration): array;
}
