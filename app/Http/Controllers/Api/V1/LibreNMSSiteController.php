<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Services\LibreNMSSiteService;
use App\Services\SiteMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LibreNMSSiteController extends Controller
{
    protected LibreNMSSiteService $siteService;

    public function __construct(LibreNMSSiteService $siteService)
    {
        $this->siteService = $siteService;
        $this->middleware('auth:sanctum');
    }

    public function locations(Request $request, Integration $integration)
    {
        $this->authorize('view', $integration);

        $result = $this->siteService->fetchDevicesWithLocations($integration);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json($result);
    }

    public function map(Request $request, Integration $integration)
    {
        $this->authorize('import', $integration);

        $validated = $request->validate([
            'devices' => 'required|array',
        ]);

        $siteMapping = app(SiteMappingService::class);
        $results = [];

        foreach ($validated['devices'] as $device) {
            $results[] = array_merge(
                $siteMapping->mapDevice($device, $integration),
                ['device_id' => $device['device_id'] ?? null]
            );
        }

        return response()->json(['data' => $results]);
    }

    public function preview(Request $request, Integration $integration)
    {
        $this->authorize('import', $integration);

        $result = $this->siteService->previewSites($integration, $request->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json($result);
    }

    public function import(Request $request, Integration $integration)
    {
        $this->authorize('import', $integration);

        $validated = $request->validate([
            'sites' => 'required|array',
        ]);

        $history = ImportHistory::create([
            'source' => ImportHistory::SOURCE_LIBRENMS,
            'type' => ImportHistory::TYPE_SITE,
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_PENDING,
            'started_by' => Auth::id(),
            'total_records' => count($validated['sites']),
        ]);

        try {
            $history->markAsRunning();
            $results = $this->siteService->execute(
                $integration,
                $request->user(),
                $validated['sites'],
                $history
            );

            $history->markAsCompleted([
                'created_records' => $results['created'],
                'updated_records' => $results['updated'],
                'skipped_records' => $results['skipped'],
                'error_records' => $results['failed'],
            ]);

            return response()->json([
                'success' => true,
                'data' => array_merge($results, ['history_id' => $history->id]),
            ]);
        } catch (\Exception $e) {
            $history->markAsFailed($e->getMessage());
            Log::error('LibreNMS site import failed', [
                'integration_id' => $integration->id,
                'exception_class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Import could not be completed. Please try again later.',
            ], 500);
        }
    }
}
