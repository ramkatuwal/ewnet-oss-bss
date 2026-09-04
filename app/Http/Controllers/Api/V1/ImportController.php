<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Imports\GenericImportService;
use App\Services\Imports\UispSourceAdapter;
use App\Services\Imports\LibreNmsSourceAdapter;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    protected function getProviderPermission(string $provider): ?string
    {
        return match ($provider) {
            'librenms' => 'librenms.import',
            'uisp' => 'integration.uisp.import',
            default => null,
        };
    }

    protected function authorizeProvider(Integration $integration): void
    {
        $user = auth()->user();
        
        // Super Admin bypass
        if ($user->hasRole('Super Admin')) {
            return;
        }

        $permission = $this->getProviderPermission($integration->provider);
        
        if (!$permission) {
            abort(403, 'Unsupported provider: ' . $integration->provider);
        }

        if (!$user->hasPermissionTo($permission)) {
            abort(403, 'You do not have permission to import from ' . $integration->provider);
        }
    }

    protected function authorizeImportScope(Request $request, Integration $integration): void
    {
        // Preview and execute both need scope authorization
        // The actual scope check happens in GenericImportService
        // This ensures the user can access the integration
        $this->authorizeProvider($integration);
    }

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function providers()
    {
        try {
            $user = auth()->user();
            $integrations = Integration::where('enabled', true)->get();
            $providers = [];

            foreach ($integrations as $int) {
                try {
                    $adapter = $this->resolveAdapter($int);
                    if (!$adapter) continue;

                    // Check if user has permission for this provider
                    $permission = $this->getProviderPermission($int->provider);
                    if ($permission && !$user->hasPermissionTo($permission) && !$user->hasRole('Super Admin')) {
                        continue;
                    }

                    $providers[] = [
                        'id' => $int->id,
                        'name' => $adapter->getDisplayName(),
                        'identity' => $adapter->getIdentity(),
                        'capabilities' => $adapter->getCapabilities()
                    ];
                } catch (\Exception $e) {
                    Log::error('Import Providers: Failed to load adapter for integration ' . $int->id, [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json(['data' => $providers]);
        } catch (\Exception $e) {
            Log::error('Providers endpoint failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to load providers'], 500);
        }
    }

    public function preview(Request $request)
    {
        try {
            $request->validate([
                'integration_id' => 'required|exists:integrations,id',
                'source_type' => 'required|in:devices,sites'
            ]);

            $integration = Integration::findOrFail($request->integration_id);
            
            // Authorize provider access
            $this->authorizeProvider($integration);

            $adapter = $this->resolveAdapter($integration);
            if (!$adapter) {
                return response()->json(['error' => 'Provider not supported'], 400);
            }

            $service = new GenericImportService($adapter);
            $previewData = $service->preview($request->source_type);
            
            // Filter preview data by management scope
            $filteredData = $this->filterPreviewByScope($previewData);
            
            return response()->json(['data' => $filteredData]);
        } catch (\Exception $e) {
            Log::error('Preview endpoint failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Preview failed: ' . $e->getMessage()], 500);
        }
    }

    protected function filterPreviewByScope(array $previewData): array
    {
        $user = auth()->user();
        if ($user->hasRole('Super Admin')) {
            return $previewData;
        }

        // Filter records based on scope
        if (isset($previewData['records'])) {
            $previewData['records'] = array_filter($previewData['records'], function ($record) use ($user) {
                return $this->isRecordInScope($record, $user);
            });
            $previewData['total'] = count($previewData['records']);
        }

        return $previewData;
    }

    protected function isRecordInScope(array $record, $user): bool
    {
        // If record has a destination_id, check if user has scope for it
        if (isset($record['analysis']['destination_id'])) {
            $destinationId = $record['analysis']['destination_id'];
            $recordData = $record['record'] ?? [];
            $type = $recordData['source_type'] ?? null;

            if ($type === 'site') {
                $site = \App\Models\Site::find($destinationId);
                if ($site) {
                    return ManagementScopeService::isInScope($user, $site);
                }
            } elseif ($type === 'device') {
                $asset = \App\Models\Asset::find($destinationId);
                if ($asset) {
                    return ManagementScopeService::isInScope($user, $asset);
                }
            }
        }

        // If no destination_id, allow preview (will be filtered during execute)
        return true;
    }

    public function execute(Request $request)
    {
        try {
            $request->validate([
                'integration_id' => 'required|exists:integrations,id',
                'items' => 'required|array'
            ]);

            $integration = Integration::findOrFail($request->integration_id);
            
            // Authorize provider access
            $this->authorizeProvider($integration);

            $adapter = $this->resolveAdapter($integration);
            if (!$adapter) {
                return response()->json(['error' => 'Provider not supported'], 400);
            }

            $service = new GenericImportService($adapter);
            $result = $service->execute($request->items);

            return response()->json(['data' => $result]);
        } catch (\Exception $e) {
            Log::error('Execute endpoint failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Execution failed: ' . $e->getMessage()], 500);
        }
    }

    protected function resolveAdapter(Integration $integration): ?object
    {
        return match ($integration->provider) {
            'uisp' => new UispSourceAdapter($integration),
            'librenms' => new LibreNmsSourceAdapter($integration),
            default => null
        };
    }
}
