<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegionRequest;
use App\Http\Resources\V1\RegionResource;
use App\Models\Region;
use App\Services\AuditService;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Region::class);

        $query = Region::with('company')->withCount('branches');

        if (!$request->user()->hasRole('Super Admin')) {
            $query->where('company_id', $request->user()->company_id);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhere('city', 'ilike', "%{$search}%");
            });
        }

        $regions = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return RegionResource::collection($regions);
    }

    public function store(RegionRequest $request)
    {
        $this->authorize('create', Region::class);

        if (!$request->user()->hasRole('Super Admin')) {
            if ($request->company_id != $request->user()->company_id) {
                abort(403, 'Cannot create region in another company');
            }
        }

        $region = Region::create($request->validated());
        AuditService::log('region.create', 'success', $region);

        return new RegionResource($region->load('company')->loadCount('branches'));
    }

    public function show(Request $request, Region $region)
    {
        $this->authorize('view', $region);

        return new RegionResource($region->load('company')->loadCount('branches'));
    }

    public function update(RegionRequest $request, Region $region)
    {
        $this->authorize('update', $region);

        if ($request->has('company_id') && !$request->user()->hasRole('Super Admin')) {
            if ($request->company_id != $request->user()->company_id) {
                abort(403, 'Cannot move region to another company');
            }
        }

        $region->update($request->validated());
        AuditService::log('region.update', 'success', $region);

        return new RegionResource($region->fresh()->load('company')->loadCount('branches'));
    }

    public function destroy(Request $request, Region $region)
    {
        $this->authorize('delete', $region);

        if ($region->branches()->exists()) {
            abort(422, 'Cannot delete region with existing branches. Delete or reassign branches first.');
        }

        $region->delete();
        AuditService::log('region.delete', 'success', $region);

        return response()->json(['message' => 'Region deleted successfully']);
    }
}
