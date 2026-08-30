<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSiteRequest;
use App\Http\Requests\Api\V1\UpdateSiteRequest;
use App\Http\Resources\V1\SiteResource;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use App\Services\SiteExportService;
use App\Jobs\ProcessSiteImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Site::class);

        $query = Site::with(['company', 'region', 'branch']);
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), Site::class);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('site_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->input('region_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $sites = $query->paginate($request->input('per_page', 15));

        return SiteResource::collection($sites);
    }

    public function store(StoreSiteRequest $request)
    {
        $site = Site::create($request->validated());

        AuditService::log('site.created', 'success', $site, $request->validated());

        return new SiteResource($site->load(['company', 'region', 'branch']));
    }

    public function show(Site $site)
    {
        $this->authorize('view', $site);

        return new SiteResource($site->load(['company', 'region', 'branch']));
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $site->update($request->validated());

        AuditService::log('site.updated', 'success', $site, $request->validated());

        return new SiteResource($site->load(['company', 'region', 'branch']));
    }

    public function destroy(Site $site)
    {
        $this->authorize('delete', $site);

        $site->delete();

        AuditService::log('site.deleted', 'success', $site);

        return response()->json(['message' => 'Site deleted successfully.']);
    }

    public function import(Request $request)
    {
        $this->authorize('sites.import');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        $path = $request->file('file')->store('imports', 'local');
        $fullPath = storage_path('app/' . $path);

        ProcessSiteImport::dispatch($fullPath, $request->user()->id);

        return response()->json(['message' => 'Import queued successfully. Check Horizon for status.'], 202);
    }

    public function export(Request $request, SiteExportService $exportService)

    {

        $this->authorize('sites.export');

        $format = $request->input('format', 'csv');

        $filters = $request->only(['search']);



        if ($format === 'xlsx') {

            return $exportService->exportXlsx($request->user(), $filters);

        }

        return $exportService->exportCsv($request->user(), $filters);

    }
}
