<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BranchRequest;
use App\Http\Resources\V1\BranchResource;
use App\Models\Branch;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Branch::class);

        $query = Branch::with('region.company');

        // Apply centralized scope filtering
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), Branch::class);

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->get('region_id'));
        }

        if ($request->filled('company_id')) {
            $query->whereHas('region', function ($q) use ($request) {
                $q->where('company_id', $request->get('company_id'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhere('city', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $branches = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return BranchResource::collection($branches);
    }

    public function store(BranchRequest $request)
    {
        $this->authorize('create', Branch::class);

        if (!$request->user()->hasRole('Super Admin')) {
            $region = \App\Models\Region::findOrFail($request->region_id);
            if ($region->company_id != $request->user()->company_id) {
                abort(403, 'Cannot create branch in another company\'s region');
            }
        }

        $branch = Branch::create($request->validated());
        AuditService::log('branch.create', 'success', $branch);

        return new BranchResource($branch->load('region.company'));
    }

    public function show(Request $request, Branch $branch)
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch->load('region.company'));
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        if ($request->has('region_id') && !$request->user()->hasRole('Super Admin')) {
            $region = \App\Models\Region::findOrFail($request->region_id);
            if ($region->company_id != $request->user()->company_id) {
                abort(403, 'Cannot move branch to another company\'s region');
            }
        }

        $branch->update($request->validated());
        AuditService::log('branch.update', 'success', $branch);

        return new BranchResource($branch->fresh()->load('region.company'));
    }

    public function destroy(Request $request, Branch $branch)
    {
        $this->authorize('delete', $branch);

        $branch->delete();
        AuditService::log('branch.delete', 'success', $branch);

        return response()->json(['message' => 'Branch deleted successfully']);
    }
}
