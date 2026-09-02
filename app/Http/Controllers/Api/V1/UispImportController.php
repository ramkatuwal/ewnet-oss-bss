<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Integrations\Uisp\UispImportService;
use App\Services\Integrations\Uisp\UispDuplicateDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UispImportController extends Controller
{
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
            $detector = new UispDuplicateDetector();

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
