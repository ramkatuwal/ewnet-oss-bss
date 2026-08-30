<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Services\LibreNMSSiteService;
use Illuminate\Http\Request;

class LibreNMSSiteController extends Controller
{
    protected LibreNMSSiteService $siteService;

    public function __construct(LibreNMSSiteService $siteService)
    {
        $this->siteService = $siteService;
    }

    /**
     * Get LibreNMS locations
     */
    public function locations(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $result = $this->siteService->fetchDevicesWithLocations($integration);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json([
            'data' => $result['locations'],
            'count' => $result['count'],
            'device_count' => count($result['devices'] ?? []),
        ]);
    }

    /**
     * Preview site import/mapping
     */
    public function preview(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $result = $this->siteService->previewSites($integration, $request->user());

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json($result);
    }

    /**
     * Map a location to an EWNET Site
     */
    public function map(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $request->validate([
            'location_name' => ['required', 'string'],
            'site_id' => ['required', 'exists:sites,id'],
        ]);

        $result = $this->siteService->mapLocation(
            $integration,
            $request->location_name,
            $request->site_id,
            $request->user()
        );

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Location mapped successfully',
            'data' => $result['reference'],
        ]);
    }

    /**
     * Import sites from LibreNMS
     */
    public function import(Request $request, Integration $integration)
    {
        $this->authorize('librenms.import');

        $request->validate([
            'dry_run' => ['sometimes', 'boolean'],
            'locations' => ['sometimes', 'array'],
            'locations.*' => ['string'],
        ]);

        $options = [
            'dry_run' => $request->input('dry_run', false),
            'locations' => $request->input('locations'),
        ];

        $result = $this->siteService->importSites($integration, $request->user(), $options);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 500);
        }

        return response()->json([
            'message' => $options['dry_run'] ? 'Dry run completed.' : 'Import completed.',
            'results' => $result,
        ]);
    }
}
