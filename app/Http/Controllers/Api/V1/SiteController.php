<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSiteRequest;
use App\Http\Requests\Api\V1\UpdateSiteRequest;
use App\Http\Resources\V1\SiteResource;
use App\Models\Site;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Site::class);

        $query = Site::query();

        // Apply Management Scope
        $query = \App\Services\ManagementScopeService::applyScopeToQuery($query, $request->user(), Site::class);

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
}
