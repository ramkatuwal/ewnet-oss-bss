<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Imports\GenericImportService;
use App\Services\Imports\UispSourceAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('can:integration.uisp.import');
    }

    public function providers()
    {
        try {
            $integrations = Integration::where('enabled', true)->get();
            $providers = [];
            
            foreach ($integrations as $int) {
                if ($int->provider === 'uisp') {
                    try {
                        $adapter = new UispSourceAdapter($int);
                        $providers[] = [
                            'id' => $int->id,
                            'name' => $adapter->getDisplayName(),
                            'identity' => $adapter->getIdentity(),
                            'capabilities' => $adapter->getCapabilities()
                        ];
                    } catch (\Exception $e) {
                        Log::warning('Failed to load UISP adapter for integration ' . $int->id, ['error' => $e->getMessage()]);
                    }
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
            
            if ($integration->provider === 'uisp') {
                $adapter = new UispSourceAdapter($integration);
                $service = new GenericImportService($adapter);
                return response()->json(['data' => $service->preview($request->source_type)]);
            }

            return response()->json(['error' => 'Provider not supported yet'], 400);
        } catch (\Exception $e) {
            Log::error('Preview endpoint failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Preview failed: ' . $e->getMessage()], 500);
        }
    }

    public function execute(Request $request)
    {
        try {
            $request->validate([
                'integration_id' => 'required|exists:integrations,id',
                'items' => 'required|array'
            ]);

            $integration = Integration::findOrFail($request->integration_id);
            
            if ($integration->provider === 'uisp') {
                $adapter = new UispSourceAdapter($integration);
                $service = new GenericImportService($adapter);
                return response()->json(['data' => $service->execute($request->items)]);
            }

            return response()->json(['error' => 'Provider not supported yet'], 400);
        } catch (\Exception $e) {
            Log::error('Execute endpoint failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Execution failed: ' . $e->getMessage()], 500);
        }
    }
}
