<?php

namespace App\Services\Observations;

use App\Jobs\RunObservationSync;
use App\Models\Integration;
use App\Models\IntegrationSync;
use App\Services\AuditService;

class ObservationSyncManager
{
    public static function start(Integration $integration, string $category, ?int $userId = null): IntegrationSync
    {
        $sync = IntegrationSync::create([
            'integration_id' => $integration->id,
            'operation' => $category, // interfaces | vlans | all
            'status' => 'pending',
            'initiated_by' => $userId,
            'metadata' => [
                'domain' => 'observations',
                'category' => $category,
            ],
        ]);

        RunObservationSync::dispatch($sync);

        AuditService::log('observation.sync_started', 'success', $integration, [
            'sync_id' => $sync->id,
            'category' => $category,
        ]);

        return $sync;
    }
}
