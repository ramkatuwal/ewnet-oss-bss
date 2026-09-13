<?php

namespace App\Contracts;

use App\Dto\Observations\ObservedDevice;
use App\Models\Integration;

/**
 * Fetches provider observations and normalizes them into provider-neutral
 * records. Implementations MUST NOT open transactions, write to the database,
 * or perform reconciliation — that is the observation sync service's job.
 */
interface ProviderObservationSource
{
    /**
     * @return array<int, ObservedDevice>
     */
    public function fetch(Integration $integration): array;
}
