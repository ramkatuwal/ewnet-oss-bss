<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Services\LibreNMSSiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LibreNMSSiteController extends Controller
{
    protected LibreNMSSiteService $siteService;

    public function __construct(LibreNMSSiteService $siteService)
    {
        $this->siteService = $siteService;
        $this->middleware('auth:sanctum');
    }

    public function preview(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');
        $result = $this->siteService->previewSites($integration, $request->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json($result);
    }

    public function import(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

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
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
