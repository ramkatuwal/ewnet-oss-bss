<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ImportHistoryResource;
use App\Models\ImportHistory;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class ImportHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $hasAccess = $user->isSuperAdmin()
            || $user->hasPermissionTo('logs.view')
            || $user->hasPermissionTo('librenms.import')
            || $user->hasPermissionTo('integration.uisp.import');

        abort_unless($hasAccess, 403);

        $query = ImportHistory::query()
            ->with(['integration', 'startedBy'])
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->get('source')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->get('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->orderByDesc('created_at');

        if (! ManagementScopeService::hasGlobalScope($user)) {
            $allowedIntegrationIds = ManagementScopeService::resolveAllowedIntegrationIds($user);
            $query->whereIn('integration_id', $allowedIntegrationIds);
        }

        $records = $query->paginate($request->integer('per_page', 15));

        return ImportHistoryResource::collection($records);
    }
}
