<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSplitterBranchRequest;
use App\Http\Resources\V1\SplitterBranchResource;
use App\Models\SplitterBranch;
use App\Models\SplitterProfile;
use App\Services\AuditService;
use App\Services\Fim\SplitterBranchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SplitterBranchController extends Controller
{
    public function __construct(protected SplitterBranchService $branches) {}

    public function index(Request $request, SplitterProfile $splitterProfile)
    {
        $this->authorize('viewAny', SplitterBranch::class);

        $branches = $splitterProfile->branches()
            ->with(['inputPort', 'outputPort'])
            ->latest()
            ->get()
            ->filter(fn (SplitterBranch $branch) => $request->user()->can('view', $branch))
            ->values();

        $perPage = $request->integer('per_page', 15);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return SplitterBranchResource::collection(new LengthAwarePaginator(
            $branches->forPage($page, $perPage),
            $branches->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        ));
    }

    public function store(StoreSplitterBranchRequest $request, SplitterProfile $splitterProfile)
    {
        $branch = $this->branches->create($splitterProfile, $request->validated(), $request->user());
        AuditService::log('fim.splitter-branch.created', 'success', $branch, $branch->only(['id', 'company_id', 'splitter_profile_id', 'input_port_id', 'output_port_id']));

        return (new SplitterBranchResource($branch))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, SplitterBranch $splitterBranch)
    {
        $metadata = $splitterBranch->only(['id', 'company_id', 'splitter_profile_id', 'input_port_id', 'output_port_id']);
        $this->branches->delete($splitterBranch, $request->user());
        AuditService::log('fim.splitter-branch.deleted', 'success', $splitterBranch, $metadata);

        return response()->json(['message' => 'Splitter branch deleted successfully.']);
    }
}
