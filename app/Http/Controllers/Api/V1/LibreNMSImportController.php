<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\LibreNMSImportService;
use Illuminate\Http\Request;

class LibreNMSImportController extends Controller
{
    protected LibreNMSImportService $importService;

    public function __construct(LibreNMSImportService $importService)
    {
        $this->importService = $importService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Get list of devices from LibreNMS
     */
    public function devices(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $result = $this->importService->fetchDevices($integration);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json([
            'data' => $result['devices'],
            'count' => $result['count'],
        ]);
    }

    /**
     * Preview import without making changes
     */
    public function preview(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $result = $this->importService->preview($integration, $request->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json($result);
    }

    /**
     * Execute import
     */
    public function import(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $request->validate([
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $options = [
            'dry_run' => $request->input('dry_run', false),
        ];

        $result = $this->importService->import($integration, $request->user(), $options);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json([
            'message' => $options['dry_run'] ? 'Dry run completed.' : 'Import completed.',
            'results' => $result,
        ]);
    }
}
