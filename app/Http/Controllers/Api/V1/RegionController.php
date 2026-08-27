<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegionRequest;
use App\Http\Resources\V1\RegionResource;
use App\Models\Region;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Region::class);

        $query = Region::query();

        // Scope to user's company if not Super Admin
        if (!$request->user()->hasRole('Super Admin')) {
            $query->where('company_id', $request->user()->company_id);
        }

        $regions = $query->paginate($request->get('per_page', 15));

        return RegionResource::collection($regions);
    }

    public function store(RegionRequest $request)
    {
        $this->authorize('create', Region::class);

        // Validate company_id is within user's scope
        if (!$request->user()->hasRole('Super Admin')) {
            if ($request->company_id !== $request->user()->company_id) {
                abort(403, 'Cannot create region in another company');
            }
        }

        $region = Region::create($request->validated());

        return new RegionResource($region);
    }

    public function show(Request $request, Region $region)
    {
        $this->authorize('view', $region);

        return new RegionResource($region);
    }

    public function update(RegionRequest $request, Region $region)
    {
        $this->authorize('update', $region);

        // Validate company_id if being changed
        if ($request->has('company_id') && !$request->user()->hasRole('Super Admin')) {
            if ($request->company_id !== $request->user()->company_id) {
                abort(403, 'Cannot move region to another company');
            }
        }

        $region->update($request->validated());

        return new RegionResource($region);
    }

    public function destroy(Request $request, Region $region)
    {
        $this->authorize('delete', $region);

        $region->update(['is_active' => false]);
        $region->delete();

        return response()->noContent();
    }
}
