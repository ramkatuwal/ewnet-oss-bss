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
        // Using a generic permission for now, can be refined to 'import.execute'
        $this->middleware('can:integration.uisp.import'); 
    }

    public function providers()
    {
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
                    Log::warning('Failed to load UISP adapter for integration ' . $int->id);
                }
            }
            // Add LibreNMS adapter here when ready
        }
        
        return response()->json(['data' => $providers]);
    }

    public function preview(Request $request)
    {
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
    }

    public function execute(Request $request)
    {
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
    }
}
