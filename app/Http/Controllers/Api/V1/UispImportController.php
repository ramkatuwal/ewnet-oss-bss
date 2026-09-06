<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Services\Integrations\Uisp\UispImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UispImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('can:integration.uisp.import');
    }

    public function preview(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('integration.uisp.import', $integration);

        try {
            $service = new UispImportService($integration);
            $result = $service->preview();

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('UISP import preview failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Preview failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function execute(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('integration.uisp.import', $integration);

        $validated = $request->validate([
            'sites' => 'array',
            'devices' => 'array',
        ]);

        $history = ImportHistory::create([
            'source' => ImportHistory::SOURCE_UISP,
            'type' => 'mixed', // Or determine based on content
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_PENDING,
            'started_by' => Auth::id(),
            'total_records' => count($validated['sites'] ?? []) + count($validated['devices'] ?? []),
        ]);

        try {
            $history->markAsRunning();
            $service = new UispImportService($integration);
            $result = $service->execute($validated['sites'] ?? [], $validated['devices'] ?? [], $history);

            $history->markAsCompleted([
                'created_records' => $result['created'] ?? 0,
                'updated_records' => $result['updated'] ?? 0,
                'skipped_records' => $result['skipped'] ?? 0,
                'error_records' => $result['failed'] ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'data' => array_merge($result, ['history_id' => $history->id]),
            ]);
        } catch (\Exception $e) {
            $history->markAsFailed($e->getMessage());
            Log::error('UISP import execution failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function analyzeSingle(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('integration.uisp.import', $integration);

        $type = $request->input('type');
        $data = $request->input('data');

        if (!$data) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        try {
            $detector = new \App\Services\Integrations\Uisp\UispDuplicateDetector();

            if ($type === 'site') {
                $result = $detector->analyzeSite($data);
            } elseif ($type === 'device') {
                $result = $detector->analyzeDevice($data);
            } else {
                return response()->json(['error' => 'Invalid type'], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Analysis failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
