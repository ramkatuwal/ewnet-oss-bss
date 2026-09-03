<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\Imports\GenericImportService;
use App\Services\Imports\UispSourceAdapter;
use App\Services\Imports\LibreNmsSourceAdapter;
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
            Log::info('Import Providers: Starting');
            $integrations = Integration::where('enabled', true)->get();
            Log::info('Import Providers: Found ' . $integrations->count() . ' enabled integrations');
            
            $providers = [];
            
            foreach ($integrations as $int) {
                try {
                    Log::info('Import Providers: Processing integration ' . $int->id . ' (' . $int->provider . ')');
                    $adapter = $this->resolveAdapter($int);
                    if ($adapter) {
                        Log::info('Import Providers: Adapter resolved for ' . $int->id);
                        $providers[] = [
                            'id' => $int->id,
                            'name' => $adapter->getDisplayName(),
                            'identity' => $adapter->getIdentity(),
                            'capabilities' => $adapter->getCapabilities()
                        ];
                    } else {
                        Log::warning('Import Providers: Adapter returned null for ' . $int->id);
                    }
                } catch (\Exception $e) {
                    Log::error('Import Providers: Failed to load adapter for integration ' . $int->id, [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            Log::info('Import Providers: Returning ' . count($providers) . ' providers');
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
            $adapter = $this->resolveAdapter($integration);
            
            if (!$adapter) {
                return response()->json(['error' => 'Provider not supported'], 400);
            }

            $service = new GenericImportService($adapter);
            return response()->json(['data' => $service->preview($request->source_type)]);
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
            $adapter = $this->resolveAdapter($integration);
            
            if (!$adapter) {
                return response()->json(['error' => 'Provider not supported'], 400);
            }

            $service = new GenericImportService($adapter);
            return response()->json(['data' => $service->execute($request->items)]);
        } catch (\Exception $e) {
            Log::error('Execute endpoint failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Execution failed: ' . $e->getMessage()], 500);
        }
    }

    protected function resolveAdapter(Integration $integration): ?object
    {
        return match ($integration->provider) {
            'uisp' => new UispSourceAdapter($integration),
            'librenms' => new LibreNmsSourceAdapter($integration),
            default => null
        };
    }
}
