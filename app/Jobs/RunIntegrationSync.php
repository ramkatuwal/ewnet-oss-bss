<?php

namespace App\Jobs;

use App\Models\IntegrationSync;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunIntegrationSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $backoff = 60;

    public function __construct(
        private IntegrationSync $sync
    ) {}

    public function handle(): void
    {
        $this->sync->refresh();
        $this->sync->load('integration');

        if (!$this->sync->integration) {
            Log::error("RunIntegrationSync: Integration not found for sync ID {$this->sync->id}");
            $this->sync->markFailed('Integration not found');
            return;
        }

        if (!$this->sync->integration->enabled) {
            Log::warning("RunIntegrationSync: Integration disabled for sync ID {$this->sync->id}");
            $this->sync->markFailed('Integration is disabled');
            return;
        }

        $this->sync->markRunning();

        try {
            $provider = IntegrationManager::resolve($this->sync->integration->provider);
            $result = $provider->synchronize($this->sync->integration);

            $this->sync->markCompleted([
                'records_processed' => $result['processed'] ?? 0,
                'records_created' => $result['created'] ?? 0,
                'records_updated' => $result['updated'] ?? 0,
                'records_unchanged' => $result['unchanged'] ?? 0,
                'records_skipped' => $result['skipped'] ?? 0,
                'records_failed' => $result['failed'] ?? 0,
            ]);

            $this->sync->integration->update(['last_sync_at' => now()]);

            \App\Services\AuditService::log('integration.sync_completed', 'success', $this->sync->integration, [
                'sync_id' => $this->sync->id,
                'processed' => $result['processed'] ?? 0,
                'created' => $result['created'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'failed' => $result['failed'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error("Integration sync failed: {$e->getMessage()}", [
                'sync_id' => $this->sync->id,
                'integration_id' => $this->sync->integration_id,
            ]);

            $safeMessage = preg_replace(
                '/(password|token|secret|key|auth|credential)=\S+/i',
                '$1=[REDACTED]',
                $e->getMessage()
            );

            $this->sync->markFailed(mb_substr($safeMessage, 0, 500));

            \App\Services\AuditService::log('integration.sync_failed', 'failure', $this->sync->integration, [
                'sync_id' => $this->sync->id,
                'error' => mb_substr($safeMessage, 0, 500),
            ]);

            throw $e;
        }
    }
}
