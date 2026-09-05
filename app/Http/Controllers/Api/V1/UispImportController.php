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

    public function preview(Request $request): JsonResponse
    {
        $integration = $this->getIntegration($request);
        if (!$integration) {
            return response()->json(['error' => 'UISP integration not found'], 404);
        }

        try {
            $service = new UispImportService($integration);
            $preview = $service->preview();

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (\Exception $e) {
            Log::error('UISP import preview failed', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Preview failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function execute(Request $request): JsonResponse
    {
        $integration = $this->getIntegration($request);
        if (!$integration) {
            return response()->json(['error' => 'UISP integration not found'], 404);
        }

        $validated = $request->validate([
            'sites' => 'array',
            'devices' => 'array',
            'type' => 'required|in:device,site',
        ]);

        $history = ImportHistory::create([
            'source' => ImportHistory::SOURCE_UISP,
            'type' => $validated['type'],
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_PENDING,
            'started_by' => Auth::id(),
            'total_records' => count($validated['sites'] ?? []) + count($validated['devices'] ?? []),
            'metadata' => ['selected_ids' => array_merge(
                array_column($validated['sites'] ?? [], 'external_id'),
                array_column($validated['devices'] ?? [], 'external_id')
            )],
        ]);

        try {
            $history->markAsRunning();
            $service = new UispImportService($integration);
            $results = $service->execute($validated, $history);

            $stats = [
                'created_records' => ($results['sites']['created'] ?? 0) + ($results['devices']['created'] ?? 0),
                'updated_records' => ($results['sites']['updated'] ?? 0) + ($results['devices']['updated'] ?? 0),
                'skipped_records' => ($results['sites']['skipped'] ?? 0) + ($results['devices']['skipped'] ?? 0),
                'conflict_records' => ($results['sites']['conflicts'] ?? 0) + ($results['devices']['conflicts'] ?? 0),
                'error_records' => ($results['sites']['failed'] ?? 0) + ($results['devices']['failed'] ?? 0),
            ];
            
            $history->markAsCompleted($stats);

            return response()->json([
                'success' => true,
                'data' => array_merge($results, ['history_id' => $history->id]),
            ]);
        } catch (\Exception $e) {
            Log::error('UISP import execution failed', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id,
                'history_id' => $history->id,
            ]);
            $history->markAsFailed($e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function analyzeSingle(Request $request): JsonResponse
    {
        $integration = $this->getIntegration($request);
        if (!$integration) {
            return response()->json(['error' => 'UISP integration not found'], 404);
        }

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
                return response()->json(['error' => 'Invalid type. Must be "site" or "device"'], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('UISP single analysis failed', [
                'error' => $e->getMessage(),
                'type' => $type,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Analysis failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function getIntegration(Request $request): ?Integration
    {
        $id = $request->input('integration_id');
        if ($id) {
            return Integration::where('id', $id)->where('provider', 'uisp')->first();
        }

        return Integration::where('provider', 'uisp')->first();
    }
}
