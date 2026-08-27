<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BranchRequest;
use App\Http\Resources\V1\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Branch::class);

        $query = Branch::query();

        // Scope to user's region if not Super Admin
        if (!$request->user()->hasRole('Super Admin')) {
            $query->where('region_id', $request->user()->branch?->region_id);
        }

        $branches = $query->paginate($request->get('per_page', 15));

        return BranchResource::collection($branches);
    }

    public function store(BranchRequest $request)
    {
        $this->authorize('create', Branch::class);

        // Validate region_id is within user's scope
        if (!$request->user()->hasRole('Super Admin')) {
            $userRegionId = $request->user()->branch?->region_id;
            if ($request->region_id !== $userRegionId) {
                abort(403, 'Cannot create branch in another region');
            }
        }

        $branch = Branch::create($request->validated());

        return new BranchResource($branch);
    }

    public function show(Request $request, Branch $branch)
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch);
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        // Validate region_id if being changed
        if ($request->has('region_id') && !$request->user()->hasRole('Super Admin')) {
            $userRegionId = $request->user()->branch?->region_id;
            if ($request->region_id !== $userRegionId) {
                abort(403, 'Cannot move branch to another region');
            }
        }

        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Request $request, Branch $branch)
    {
        $this->authorize('delete', $branch);

        $branch->update(['is_active' => false]);
        $branch->delete();

        return response()->noContent();
    }
}
