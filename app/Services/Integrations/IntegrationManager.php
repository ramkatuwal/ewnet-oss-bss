<?php

namespace App\Services\Integrations;

use App\Contracts\IntegrationProviderInterface;
use App\Models\Integration;
use App\Models\IntegrationSync;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;

class IntegrationManager
{
    /** @var array<string, class-string<IntegrationProviderInterface>> */
    private static array $providers = [];

    public static function register(string $providerKey, string $providerClass): void
    {
        if (!is_subclass_of($providerClass, IntegrationProviderInterface::class)) {
            throw new \InvalidArgumentException("{$providerClass} must implement IntegrationProviderInterface");
        }
        static::$providers[$providerKey] = $providerClass;
    }

    public static function resolve(string $providerKey): IntegrationProviderInterface
    {
        if (!isset(static::$providers[$providerKey])) {
            throw new \InvalidArgumentException("Unknown integration provider: {$providerKey}");
        }
        return app(static::$providers[$providerKey]);
    }

    public static function availableProviders(): array
    {
        return array_keys(static::$providers);
    }

    public static function testConnection(Integration $integration): array
    {
        try {
            $provider = static::resolve($integration->provider);
            $result = $provider->testConnection($integration);

            $integration->update([
                'status' => ($result['success'] ?? false) ? 'connected' : 'failed',
                'last_health_check_at' => now(),
            ]);

            AuditService::log('integration.connection_tested', $result['success'] ? 'success' : 'failure', $integration, [
                'response_time_ms' => $result['response_time_ms'] ?? null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("Integration connection test failed: {$e->getMessage()}", [
                'integration_id' => $integration->id,
            ]);

            $integration->update(['status' => 'failed', 'last_health_check_at' => now()]);

            AuditService::log('integration.health_check_failed', 'failure', $integration, [
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Connection test failed'];
        }
    }

    public static function healthCheck(Integration $integration): array
    {
        try {
            $provider = static::resolve($integration->provider);
            $result = $provider->healthCheck($integration);

            $newStatus = match ($result['status'] ?? 'unknown') {
                'connected' => 'connected',
                'degraded' => 'degraded',
                default => 'failed',
            };

            $integration->update([
                'status' => $newStatus,
                'last_health_check_at' => now(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("Integration health check failed: {$e->getMessage()}", [
                'integration_id' => $integration->id,
            ]);

            $integration->update(['status' => 'failed', 'last_health_check_at' => now()]);

            return ['status' => 'failed', 'error' => 'Health check failed'];
        }
    }

    public static function triggerSync(Integration $integration, string $operation = 'full', ?int $userId = null): IntegrationSync
    {
        $sync = IntegrationSync::create([
            'integration_id' => $integration->id,
            'operation' => $operation,
            'status' => 'pending',
            'initiated_by' => $userId,
        ]);

        \App\Jobs\RunIntegrationSync::dispatch($sync);

        AuditService::log('integration.sync_started', 'success', $integration, [
            'sync_id' => $sync->id,
            'operation' => $operation,
        ]);

        return $sync;
    }
}
