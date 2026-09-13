<?php

namespace App\Jobs;

use App\Models\IntegrationSync;
use App\Services\AuditService;
use App\Services\Observations\ObservationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Executes a provider observation sync (interfaces / vlans / all) against a
 * single integration. Concurrency-safe: the underlying service holds a
 * deterministic distributed lock per integration + category, so duplicate
 * dispatches cannot corrupt observations.
 */
class RunObservationSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $backoff = 120;

    public function __construct(
        private IntegrationSync $sync
    ) {}

    public function handle(): void
    {
        $this->sync->refresh();
        $this->sync->load('integration');

        if (! $this->sync->integration) {
            Log::error("RunObservationSync: Integration not found for sync ID {$this->sync->id}");
            $this->sync->markFailed('Integration not found');

            return;
        }

        if (! $this->sync->integration->enabled) {
            Log::warning("RunObservationSync: Integration disabled for sync ID {$this->sync->id}");
            $this->sync->markFailed('Integration is disabled');

            return;
        }

        $this->sync->markRunning();

        try {
            $service = app(ObservationSyncService::class);
            $result = $service->run($this->sync->integration, $this->sync->operation);

            if (($result['locked'] ?? false) === true) {
                $message = $result['error'] ?? 'Another observation sync is already running for this integration.';
                $this->sync->markFailed($message);

                AuditService::log('observation.sync_blocked', 'failure', $this->sync->integration, [
                    'sync_id' => $this->sync->id,
                    'category' => $this->sync->operation,
                    'error' => $message,
                ]);

                return;
            }

            $this->sync->markCompleted([
                'records_processed' => $result['records_processed'] ?? 0,
                'records_created' => $result['records_created'] ?? 0,
                'records_updated' => $result['records_updated'] ?? 0,
                'records_unchanged' => 0,
                'records_skipped' => $result['records_skipped'] ?? 0,
                'records_failed' => $result['records_failed'] ?? 0,
            ]);

            $this->sync->update([
                'metadata' => array_merge($this->sync->metadata ?? [], [
                    'observation_counts' => $result,
                ]),
            ]);

            $this->sync->integration->update(['last_sync_at' => now()]);

            AuditService::log('observation.sync_completed', 'success', $this->sync->integration, [
                'sync_id' => $this->sync->id,
                'category' => $this->sync->operation,
                'processed' => $result['records_processed'] ?? 0,
                'created' => $result['records_created'] ?? 0,
                'updated' => $result['records_updated'] ?? 0,
                'skipped' => $result['records_skipped'] ?? 0,
                'failed' => $result['records_failed'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error("Observation sync failed: {$e->getMessage()}", [
                'sync_id' => $this->sync->id,
                'integration_id' => $this->sync->integration_id,
            ]);

            $safeMessage = preg_replace(
                '/(password|token|secret|key|auth|credential)=\S+/i',
                '$1=[REDACTED]',
                $e->getMessage()
            );

            $this->sync->markFailed(mb_substr($safeMessage, 0, 500));

            AuditService::log('observation.sync_failed', 'failure', $this->sync->integration, [
                'sync_id' => $this->sync->id,
                'error' => mb_substr($safeMessage, 0, 500),
            ]);

            throw $e;
        }
    }
}
