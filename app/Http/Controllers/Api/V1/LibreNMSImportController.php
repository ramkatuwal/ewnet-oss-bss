<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Services\LibreNMSImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LibreNMSImportController extends Controller
{
    protected LibreNMSImportService $importService;

    public function __construct(LibreNMSImportService $importService)
    {
        $this->importService = $importService;
        $this->middleware('auth:sanctum');
    }

    public function preview(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');
        $result = $this->importService->preview($integration, $request->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json($result);
    }

    public function import(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $validated = $request->validate([
            'devices' => 'required|array',
        ]);

        $history = ImportHistory::create([
            'source' => ImportHistory::SOURCE_LIBRENMS,
            'type' => ImportHistory::TYPE_DEVICE,
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_PENDING,
            'started_by' => Auth::id(),
            'total_records' => count($validated['devices']),
        ]);

        try {
            $history->markAsRunning();
            $results = $this->importService->execute(
                $integration, 
                $request->user(), 
                $validated['devices'], 
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
