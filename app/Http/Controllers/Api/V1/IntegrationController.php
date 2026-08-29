<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IntegrationRequest;
use App\Http\Resources\V1\IntegrationResource;
use App\Http\Resources\V1\IntegrationSyncResource;
use App\Models\Integration;
use App\Services\AuditService;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Integration::class);

        $query = Integration::query()->with(['creator']);

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

    public function store(IntegrationRequest $request)
    {
        $this->authorize('create', Integration::class);

        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        $data['status'] = 'pending';

        // Validate configuration via provider if available
        try {
            $provider = IntegrationManager::resolve($data['provider']);
            $validationErrors = $provider->validateConfiguration($data['configuration'] ?? []);
            if (!empty($validationErrors)) {
                return response()->json(['errors' => ['configuration' => $validationErrors]], 422);
            }
        } catch (\InvalidArgumentException) {
            // Provider not yet registered — allow creation for future providers
        }

        $integration = Integration::create($data);

        AuditService::log('integration.created', 'success', $integration);

        return new IntegrationResource($integration->fresh());
    }

    public function show(Integration $integration)
    {
        $this->authorize('view', $integration);

        return new IntegrationResource($integration->load(['creator', 'updater']));
    }

    public function update(IntegrationRequest $request, Integration $integration)
    {
        $this->authorize('update', $integration);

        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $integration->update($data);

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

        if (!in_array($operation, ['full', 'incremental'])) {
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
}
