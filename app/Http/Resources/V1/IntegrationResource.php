<?php

namespace App\Http\Resources\V1;

use App\Models\AssetExternalReference;
use App\Models\SiteExternalReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    /** @var array<string, int> */
    private static array $providerObjectCounts = [];

    public static function resetObjectCountCache(): void
    {
        self::$providerObjectCounts = [];
    }

    private const SECRET_KEY_PARTS = [
        'password',
        'passwd',
        'token',
        'secret',
        'api_key',
        'apikey',
        'api-key',
        'auth',
        'credential',
        'key',
    ];

    public function toArray(Request $request): array
    {
        $integration = $this->resource;

        return [
            'id' => $integration->id,
            'company_id' => $integration->company_id,
            'company_scope' => $integration->company_id === null ? 'global' : 'company',
            'company' => $this->whenLoaded('company', fn () => $integration->company
                ? ['id' => $integration->company->id, 'name' => $integration->company->name]
                : null),
            'name' => $integration->name,
            'provider' => $integration->provider,
            'provider_type' => $integration->provider,
            'type' => $integration->type,
            'description' => $integration->description,
            'enabled' => $integration->enabled,
            'status' => $integration->status,
            'health_status' => $integration->status,
            'configuration' => $this->redactConfiguration($integration->configuration),
            'active_objects_count' => $this->providerObjectCount($integration->provider),
            'can' => [
                'view' => $request->user()?->can('view', $integration) ?? false,
                'update' => $request->user()?->can('update', $integration) ?? false,
                'delete' => $request->user()?->can('delete', $integration) ?? false,
                'sync' => $request->user()?->can('sync', $integration) ?? false,
                'test' => $request->user()?->can('test', $integration) ?? false,
                'view_logs' => $request->user()?->can('viewLogs', $integration) ?? false,
                'import' => $request->user()?->can('import', $integration) ?? false,
                'manage_credentials' => $request->user()?->can('manageCredentials', $integration) ?? false,
            ],
            'last_health_check_at' => $integration->last_health_check_at?->toISOString(),
            'last_sync_at' => $integration->last_sync_at?->toISOString(),
            'created_by' => $this->whenLoaded('creator', fn () => $integration->creator->name ?? null),
            'updated_by' => $this->whenLoaded('updater', fn () => $integration->updater->name ?? null),
            'created_at' => $integration->created_at?->toISOString(),
            'updated_at' => $integration->updated_at?->toISOString(),
        ];
    }

    protected function providerObjectCount(string $provider): int
    {
        if (! isset(self::$providerObjectCounts[$provider])) {
            $sites = SiteExternalReference::where('provider', $provider)->count();
            $assets = AssetExternalReference::where('provider', $provider)->count();
            self::$providerObjectCounts[$provider] = $sites + $assets;
        }

        return self::$providerObjectCounts[$provider];
    }

    protected function redactConfiguration(array $configuration): array
    {
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                $configuration[$key] = $this->redactConfiguration($value);
            } elseif ($this->isSecretKey($key)) {
                $configuration[$key] = '********';
            }
        }

        return $configuration;
    }

    protected function isSecretKey(int|string $key): bool
    {
        $normalized = strtolower((string) $key);
        foreach (self::SECRET_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}
