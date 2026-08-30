<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAssetRequest;
use App\Http\Requests\Api\V1\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\AssetExportService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::with(['site.company', 'site.region', 'site.branch']);
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), Asset::class);

        // Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('manufacturer', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->input('site_id'));
        }

        $assets = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ]
        ]);
    }

    public function store(StoreAssetRequest $request)
    {
        $asset = Asset::create($request->validated() + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        AuditService::log('asset.created', 'success', $asset, $request->validated());

        return response()->json(['data' => $asset], 201);
    }

    public function show(Asset $asset)
    {
        $this->authorize('view', $asset);

        return response()->json(['data' => $asset->load(['site.company', 'site.region', 'site.branch'])]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset)
    {
        $asset->update($request->validated() + ['updated_by' => $request->user()->id]);

        AuditService::log('asset.updated', 'success', $asset, $request->validated());

        return response()->json(['data' => $asset]);
    }

    public function destroy(Asset $asset)
    {
        $this->authorize('delete', $asset);

        $asset->delete();

        AuditService::log('asset.deleted', 'success', $asset);

        return response()->json(['message' => 'Asset deleted successfully.']);
    }

    public function dashboard(Request $request)
    {
        $this->authorize('viewAny', Asset::class);

        $user = $request->user();
        
        // Base query with scope
        $baseQuery = Asset::query();
        $baseQuery = ManagementScopeService::applyScopeToQuery($baseQuery, $user, Asset::class);

        // Total Sites with Assets (distinct site_id)
        $sitesWithAssets = $baseQuery->clone()->distinct('site_id')->count('site_id');
        
        // Total Asset Records
        $totalRecords = $baseQuery->clone()->count();
        
        // Total Inventory Units
        $totalUnits = $baseQuery->clone()->sum('quantity');

        // Status Breakdown
        $statusCounts = $baseQuery->clone()
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(quantity) as units'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'data' => [
                'sites_with_assets' => $sitesWithAssets,
                'total_records' => $totalRecords,
                'total_units' => $totalUnits,
                'by_status' => [
                    'operational' => $statusCounts['OPERATIONAL'] ?? 0,
                    'maintenance' => $statusCounts['MAINTENANCE'] ?? 0,
                    'faulty' => $statusCounts['FAULTY'] ?? 0,
                    'retired' => $statusCounts['RETIRED'] ?? 0,
                ]
            ]
        ]);
    }

    public function bySite(Site $site, Request $request)
    {
        $this->authorize('view', $site);

        $query = $site->assets();
        
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        $assets = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ]
        ]);
    }

    public function import(Request $request)
    {
        $this->authorize('assets.import');
        // Placeholder: Will implement queued import job similar to SiteImport
        return response()->json(['message' => 'Import functionality pending implementation in next iteration'], 202);
    }

    public function export(Request $request, AssetExportService $exportService)
    {
        $this->authorize('assets.export');
        $format = $request->input('format', 'csv');
        $filters = $request->only(['search']);

        if ($format === 'xlsx') {
            return $exportService->exportXlsx($request->user(), $filters);
        }
        return $exportService->exportCsv($request->user(), $filters);
    }
}
