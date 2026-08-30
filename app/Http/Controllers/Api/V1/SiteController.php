<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSiteRequest;
use App\Http\Requests\Api\V1\UpdateSiteRequest;
use App\Http\Resources\V1\SiteResource;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\SiteExportService;
use App\Jobs\ProcessSiteImport;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Site::class);

        $query = Site::query();
        $query = \App\Services\ManagementScopeService::applyScopeToQuery($query, $request->user(), Site::class);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('site_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $sites = $query->paginate($request->input('per_page', 15));

        return SiteResource::collection($sites);
    }

    public function store(StoreSiteRequest $request)
    {
        $site = Site::create($request->validated());

        AuditService::log('site.created', 'success', $site, $request->validated());

        return new SiteResource($site);
    }

    public function show(Site $site)
    {
        $this->authorize('view', $site);

        return new SiteResource($site);
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $site->update($request->validated());

        AuditService::log('site.updated', 'success', $site, $request->validated());

        return new SiteResource($site);
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
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ]);

        $extension = $request->file('file')->getClientOriginalExtension();
        $path = $request->file('file')->storeAs(
            'imports/sites',
            'import_' . time() . '.' . $extension,
            'local'
        );

        ProcessSiteImport::dispatch($path, $request->user()->id);

        return response()->json([
            'message' => 'Import queued successfully.',
            'job_status' => 'processing',
        ], 202);
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
