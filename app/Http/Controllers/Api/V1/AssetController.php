<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportAssetsRequest;
use App\Http\Requests\Api\V1\StoreAssetRequest;
use App\Http\Requests\Api\V1\UpdateAssetRequest;
use App\Http\Resources\V1\AssetOperationalResource;
use App\Http\Resources\V1\AssetResource;
use App\Jobs\ProcessAssetImport;
use App\Models\Asset;
use App\Models\Site;
use App\Services\AssetExportService;
use App\Services\AssetImportService;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::with(['site.company', 'site.region', 'site.branch', 'ipAddresses', 'interfaces']);
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), Asset::class);

        // Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'ilike', "%{$search}%")
                    ->orWhere('device_name', 'ilike', "%{$search}%")
                    ->orWhere('serial_number', 'ilike', "%{$search}%")
                    ->orWhere('manufacturer', 'ilike', "%{$search}%")
                    ->orWhere('model', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhereHas('site', function ($sq) use ($search) {
                        $sq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('site_code', 'ilike', "%{$search}%");
                    });
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

        // Organization hierarchy filters (derived via Site), all within the
        // user's management scope already applied above.
        if ($request->filled('company_id')) {
            $query->whereHas('site.company', function ($q) use ($request) {
                $q->where('id', $request->input('company_id'));
            });
        }

        if ($request->filled('region_id')) {
            $query->whereHas('site.region', function ($q) use ($request) {
                $q->where('id', $request->input('region_id'));
            });
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('site.branch', function ($q) use ($request) {
                $q->where('id', $request->input('branch_id'));
            });
        }

        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', $request->input('manufacturer'));
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        // Server-side sorting with whitelist
        $allowedSorts = ['asset_tag', 'device_name', 'type', 'category', 'status', 'manufacturer', 'model', 'serial_number', 'quantity', 'created_at', 'updated_at'];
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $assets = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => AssetResource::collection($assets->items()),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function store(StoreAssetRequest $request)
    {
        $this->authorize('create', Asset::class);

        $site = Site::findOrFail($request->validated('site_id'));

        $data = $request->validated();
        $data['company_id'] = $site->company_id;
        $data['asset_tag'] = Asset::generateTag();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $asset = Asset::create($data);

        AuditService::log('asset.created', 'success', $asset, $request->validated());

        return new AssetResource($asset->load(['site.company', 'site.region', 'site.branch', 'ipAddresses', 'interfaces']));
    }

    public function show(Asset $asset)
    {
        $this->authorize('view', $asset);

        $asset->load([
            'site.company', 'site.region', 'site.branch',
            'interfaces',
            'ipAddresses',
            'networkPorts.ponDomain.memberships.onuAsset',
            'networkPorts.switchingConfiguration.memberships.vlan',
            'routingInstances.routingL3Interfaces.addresses',
            'routingInstances.staticRoutes',
            'passiveOpticalPorts',
            'splitterProfile.branches',
            'ponMemberships.ponDomain.oltPort',
        ]);

        return new AssetOperationalResource($asset);
    }

    public function update(UpdateAssetRequest $request, Asset $asset)
    {
        $this->authorize('update', $asset);

        $asset->update($request->validated() + ['updated_by' => $request->user()->id]);

        AuditService::log('asset.updated', 'success', $asset, $request->validated());

        return new AssetResource($asset->load(['site.company', 'site.region', 'site.branch', 'ipAddresses', 'interfaces']));
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

        // Total Records MUST be counted BEFORE any groupBy mutates the query
        $totalRecords = (clone $baseQuery)->count();

        // Total Inventory Units
        $totalUnits = (clone $baseQuery)->sum('quantity');

        // Sites with Assets (distinct site_id)
        $sitesWithAssets = (clone $baseQuery)->distinct('site_id')->count('site_id');

        // Status Breakdown (use clone to avoid mutating base query)
        $statusCounts = (clone $baseQuery)
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
                ],
            ],
        ]);
    }

    public function bySite(Site $site, Request $request)
    {
        $this->authorize('view', $site);

        $query = $site->assets();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'ilike', "%{$search}%")
                    ->orWhere('serial_number', 'ilike', "%{$search}%")
                    ->orWhere('manufacturer', 'ilike', "%{$search}%")
                    ->orWhere('model', 'ilike', "%{$search}%");
            });
        }

        $assets = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => AssetResource::collection($assets->items()),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function import(ImportAssetsRequest $request, AssetImportService $importService)
    {
        $path = $request->file('file')->store('imports', 'local');
        $fullPath = storage_path('app/'.$path);

        ProcessAssetImport::dispatch($fullPath, $request->user()->id);

        return response()->json(['message' => 'Import queued successfully. Check Horizon for status.'], 202);
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
