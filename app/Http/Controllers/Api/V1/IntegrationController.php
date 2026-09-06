<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreIntegrationRequest;
use App\Http\Requests\Api\V1\UpdateIntegrationRequest;
use App\Http\Resources\V1\AuditLogResource;
use App\Http\Resources\V1\IntegrationResource;
use App\Http\Resources\V1\IntegrationSyncResource;
use App\Models\AssetExternalReference;
use App\Models\AuditLog;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\IntegrationSync;
use App\Models\SiteExternalReference;
use App\Services\AuditService;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\Uisp\UispImportService;
use App\Services\LibreNMSImportService;
use App\Services\LibreNMSSiteService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Integration::class);

        $query = Integration::query()->with(['creator', 'company']);

        if (! ManagementScopeService::hasGlobalScope($request->user())) {
            $allowedIds = ManagementScopeService::resolveAllowedIntegrationIds($request->user());
            $query->whereIn('integrations.id', $allowedIds);
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('provider', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $integrations = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return IntegrationResource::collection($integrations);
    }

    public function store(StoreIntegrationRequest $request)
    {
        $this->authorize('create', Integration::class);

        $data = $request->validated();

        unset($data['credential_type'], $data['credential_value'], $data['credential_label']);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = 'pending';

        $data['company_id'] = $this->resolveCompanyAssignment($request->user(), $data['company_id'] ?? null);

        // Validate configuration via provider if available
        try {
            $provider = IntegrationManager::resolve($data['provider']);
            $validationErrors = $provider->validateConfiguration($data['configuration'] ?? []);
            if (! empty($validationErrors)) {
                return response()->json(['errors' => ['configuration' => $validationErrors]], 422);
            }
        } catch (\InvalidArgumentException) {
            // Provider not yet registered — allow creation for future providers
        }

        $integration = Integration::create($data);

        // Handle Credential Creation if provided
        $credentialType = $request->validated('credential_type');
        $credentialValue = $request->validated('credential_value');
        if (! empty($credentialType) && ! empty($credentialValue)) {
            $cred = new IntegrationCredential([
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'credential_type' => $credentialType,
                'label' => $request->validated('credential_label') ?? 'Primary',
                'is_active' => true,
            ]);
            $cred->setSecretValue($credentialValue);
            $cred->save();

            AuditService::log('integration.credential.created', 'success', $integration);
        }

        AuditService::log('integration.created', 'success', $integration);

        return new IntegrationResource($integration->fresh());
    }

    public function show(Integration $integration)
    {
        $this->authorize('view', $integration);

        return new IntegrationResource($integration->load(['creator', 'updater', 'company']));
    }

    public function update(UpdateIntegrationRequest $request, Integration $integration)
    {
        $this->authorize('update', $integration);

        $data = $request->validated();

        if (array_key_exists('company_id', $data)) {
            $recordIsGlobal = $integration->company_id === null;
            $targetIsGlobal = $data['company_id'] === null;

            if ($targetIsGlobal && ! ManagementScopeService::hasGlobalScope($request->user())) {
                abort(403, 'Only users with a global scope can make an integration system-wide.');
            }
            if (! $targetIsGlobal && ! ManagementScopeService::canGrantScope($request->user(), 'company', $data['company_id'])) {
                abort(403, 'You do not have permission to assign this integration to the given company.');
            }
            if ($recordIsGlobal && ! ManagementScopeService::hasGlobalScope($request->user())) {
                abort(403);
            }
        } else {
            unset($data['company_id']);
        }

        $data['updated_by'] = auth()->id();

        unset($data['credential_type'], $data['credential_value'], $data['credential_label']);

        $integration->update($data);

        // Handle Credential Replacement if provided
        $credentialType = $request->validated('credential_type');
        $credentialValue = $request->validated('credential_value');
        if (! empty($credentialType) && ! empty($credentialValue)) {
            // Deactivate old credentials of the same type
            $integration->credentials()->where('credential_type', $credentialType)->update(['is_active' => false]);

            $cred = new IntegrationCredential([
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'credential_type' => $credentialType,
                'label' => $request->validated('credential_label') ?? 'Primary',
                'is_active' => true,
            ]);
            $cred->setSecretValue($credentialValue);
            $cred->save();

            AuditService::log('integration.credential.updated', 'success', $integration);
        }

        AuditService::log('integration.updated', 'success', $integration);

        return new IntegrationResource($integration->fresh());
    }

    public function destroy(Integration $integration)
    {
        $this->authorize('delete', $integration);

        $integration->delete();

        AuditService::log('integration.deleted', 'success', $integration);

        return response()->json(null, 204);
    }

    public function testConnection(Integration $integration)
    {
        $this->authorize('test', $integration);

        $result = IntegrationManager::testConnection($integration);

        return response()->json($result);
    }

    public function healthCheck(Integration $integration)
    {
        $this->authorize('test', $integration);

        $result = IntegrationManager::healthCheck($integration);

        return response()->json($result);
    }

    public function sync(Request $request, Integration $integration)
    {
        $this->authorize('sync', $integration);

        $operation = $request->input('operation', 'full');

        if (! in_array($operation, ['full', 'incremental'])) {
            return response()->json(['error' => 'Invalid operation'], 422);
        }

        $sync = IntegrationManager::triggerSync($integration, $operation, auth()->id());

        return new IntegrationSyncResource($sync);
    }

    public function syncs(Request $request, Integration $integration)
    {
        $this->authorize('viewLogs', $integration);

        $syncs = $integration->syncs()
            ->orderByDesc('started_at')
            ->paginate($request->get('per_page', 15));

        return IntegrationSyncResource::collection($syncs);
    }

    public function stats(Request $request, Integration $integration)
    {
        $this->authorize('view', $integration);

        $syncQuery = IntegrationSync::query()->where('integration_id', $integration->id);

        $totals = (new IntegrationSync)->newQuery()
            ->where('integration_id', $integration->id)
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'completed' then 1 else 0 end) as completed")
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed")
            ->selectRaw('sum(case when status = \'running\' or status = \'pending\' then 1 else 0 end) as running')
            ->selectRaw('coalesce(sum(records_created), 0) as created')
            ->selectRaw('coalesce(sum(records_updated), 0) as updated')
            ->selectRaw('coalesce(sum(records_skipped), 0) as skipped')
            ->selectRaw('coalesce(sum(records_failed), 0) as failed_records')
            ->first();

        $lastSync = $syncQuery->clone()
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        $sites = SiteExternalReference::where('provider', $integration->provider)->count();
        $assets = AssetExternalReference::where('provider', $integration->provider)->count();

        return response()->json([
            'data' => [
                'total_objects' => $sites + $assets,
                'syncs_total' => (int) ($totals->total ?? 0),
                'syncs_completed' => (int) ($totals->completed ?? 0),
                'syncs_failed' => (int) ($totals->failed ?? 0),
                'syncs_running' => (int) ($totals->running ?? 0),
                'success_rate' => ($totals->total ?? 0) > 0 ? round((($totals->completed ?? 0) / $totals->total) * 100, 1) : null,
                'records_created_total' => (int) ($totals->created ?? 0),
                'records_updated_total' => (int) ($totals->updated ?? 0),
                'records_skipped_total' => (int) ($totals->skipped ?? 0),
                'records_failed_total' => (int) ($totals->failed_records ?? 0),
                'last_sync_at' => $lastSync?->finished_at?->toISOString(),
                'last_sync_operation' => $lastSync?->operation,
                'last_sync_status' => $lastSync?->status,
                'last_sync_duration_seconds' => $lastSync?->started_at && $lastSync?->finished_at
                    ? max(0, (int) $lastSync->started_at->diffInSeconds($lastSync->finished_at))
                    : null,
                'last_error_summary' => $lastSync?->error_summary,
            ],
        ]);
    }

    public function auditLogs(Request $request, Integration $integration)
    {
        $this->authorize('viewLogs', $integration);

        $logs = AuditLog::where('target_type', Integration::class)
            ->where('target_id', $integration->id)
            ->with('actor')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return AuditLogResource::collection($logs);
    }

    /**
     * Unified preview endpoint for all integration providers.
     * POST /api/v1/integrations/{integration}/import/preview
     */
    public function importPreview(Request $request, Integration $integration)
    {
        $this->authorize('view', $integration);

        $validated = $request->validate([
            'resource_type' => 'required|in:device,site',
        ]);

        $resourceType = $validated['resource_type'];

        try {
            if ($integration->provider === 'uisp') {
                $service = app()->makeWith(
                    UispImportService::class,
                    ['integration' => $integration]
                );
                $result = $service->preview();
            } elseif ($integration->provider === 'librenms') {
                if ($resourceType === 'site') {
                    $service = app(LibreNMSSiteService::class);
                    $result = $service->previewSites($integration, $request->user());
                } else {
                    $service = app(LibreNMSImportService::class);
                    $result = $service->preview($integration, $request->user());
                }
            } else {
                return response()->json(['error' => 'Unsupported provider'], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Import preview failed', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'resource_type' => $resourceType,
                'exception_class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Preview could not be completed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Unified import execution endpoint for all integration providers.
     * POST /api/v1/integrations/{integration}/import
     */
    public function import(Request $request, Integration $integration)
    {
        $this->authorize('import', $integration);

        $validated = $request->validate([
            'sites' => 'array',
            'devices' => 'array',
        ]);

        $sites = $validated['sites'] ?? [];
        $devices = $validated['devices'] ?? [];

        if (empty($sites) && empty($devices)) {
            return response()->json(['error' => 'Provide at least one of sites or devices'], 422);
        }

        $history = ImportHistory::create([
            'source' => $integration->provider,
            'type' => ($devices && $sites) ? 'mixed' : ($devices ? 'device' : 'site'),
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_PENDING,
            'started_by' => auth()->id(),
            'total_records' => count($sites) + count($devices),
        ]);

        try {
            $history->markAsRunning();

            if ($integration->provider === 'uisp') {
                $service = app()->makeWith(
                    UispImportService::class,
                    ['integration' => $integration, 'history' => $history]
                );
                $results = $service->execute(['sites' => $sites, 'devices' => $devices]);
            } elseif ($integration->provider === 'librenms') {
                $results = [];
                if (! empty($sites)) {
                    $siteResults = app(LibreNMSSiteService::class)
                        ->execute($integration, $request->user(), $sites, $history);
                    $results = array_merge($results, $siteResults);
                }
                if (! empty($devices)) {
                    $deviceResults = app(LibreNMSImportService::class)
                        ->execute($integration, $request->user(), $devices, $history);
                    $results = array_merge($results, $deviceResults);
                }
            } else {
                return response()->json(['error' => 'Unsupported provider'], 422);
            }

            $history->markAsCompleted([
                'created_records' => $results['created'] ?? (
                    ($results['sites']['created'] ?? 0) + ($results['devices']['created'] ?? 0)
                ),
                'updated_records' => $results['updated'] ?? (
                    ($results['sites']['updated'] ?? 0) + ($results['devices']['updated'] ?? 0)
                ),
                'skipped_records' => $results['skipped'] ?? (
                    ($results['sites']['skipped'] ?? 0) + ($results['devices']['skipped'] ?? 0)
                ),
                'error_records' => $results['failed'] ?? (
                    ($results['sites']['failed'] ?? 0) + ($results['devices']['failed'] ?? 0)
                ),
            ]);

            return response()->json([
                'success' => true,
                'data' => array_merge($results, ['history_id' => $history->id]),
            ]);
        } catch (\Exception $e) {
            $history->markAsFailed($e->getMessage());
            Log::error('Integration import failed', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'exception_class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Import could not be completed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Resolve the owning company for a newly created integration.
     * Super admins may create system-wide (global) integrations; all other
     * users must bind the integration to a company they can manage.
     */
    protected function resolveCompanyAssignment($user, ?int $companyId): ?int
    {
        $companyId ??= $user->company_id;

        if ($companyId === null) {
            if (ManagementScopeService::hasGlobalScope($user)) {
                return null;
            }
            abort(403, 'A company scope is required to create this integration.');
        }

        if (! ManagementScopeService::hasGlobalScope($user)
            && ! ManagementScopeService::canGrantScope($user, 'company', $companyId)) {
            abort(403, 'You do not have permission to assign this integration to the given company.');
        }

        return $companyId;
    }
}
