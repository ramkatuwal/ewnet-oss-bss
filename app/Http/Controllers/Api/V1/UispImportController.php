<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Services\Integrations\Uisp\UispDuplicateDetector;
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
        $this->authorize('import', $integration);

        try {
            $service = app()->makeWith(UispImportService::class, ['integration' => $integration]);
            $result = $service->preview();

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('UISP import preview failed', [
                'integration_id' => $integration->id,
                'exception_class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Preview could not be completed. Please try again later.',
            ], 500);
        }
    }

    public function execute(Request $request, Integration $integration): JsonResponse
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
            'source' => ImportHistory::SOURCE_UISP,
            'type' => ! empty($sites) && ! empty($devices) ? 'mixed' : (! empty($devices) ? ImportHistory::TYPE_DEVICE : ImportHistory::TYPE_SITE),
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_PENDING,
            'started_by' => Auth::id(),
            'total_records' => count($sites) + count($devices),
        ]);

        try {
            $history->markAsRunning();
            $service = app()->makeWith(UispImportService::class, [
                'integration' => $integration,
                'history' => $history,
            ]);
            $result = $service->execute(['sites' => $sites, 'devices' => $devices]);

            $history->markAsCompleted([
                'created_records' => ($result['sites']['created'] ?? 0) + ($result['devices']['created'] ?? 0),
                'updated_records' => ($result['sites']['updated'] ?? 0) + ($result['devices']['updated'] ?? 0),
                'skipped_records' => ($result['sites']['skipped'] ?? 0) + ($result['devices']['skipped'] ?? 0),
                'error_records' => ($result['sites']['failed'] ?? 0) + ($result['devices']['failed'] ?? 0),
            ]);

            return response()->json([
                'success' => true,
                'data' => array_merge($result, ['history_id' => $history->id]),
            ]);
        } catch (\Exception $e) {
            $history->markAsFailed($e->getMessage());
            Log::error('UISP import execution failed', [
                'integration_id' => $integration->id,
                'exception_class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Import could not be completed. Please try again later.',
            ], 500);
        }
    }

    public function analyzeSingle(Request $request, Integration $integration): JsonResponse
    {
        $this->authorize('import', $integration);

        $type = $request->input('type');
        $data = $request->input('data');

        if (! $data) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        try {
            $detector = new UispDuplicateDetector;

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
            Log::error('UISP import analysis failed', [
                'integration_id' => $integration->id,
                'exception_class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Analysis could not be completed. Please try again later.',
            ], 500);
        }
    }
}
