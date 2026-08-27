<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DepartmentRequest;
use App\Http\Resources\V1\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Department::class);

        $query = Department::query();

        // Scope to user's branch if not Super Admin
        if (!$request->user()->hasRole('Super Admin')) {
            $query->where('branch_id', $request->user()->branch_id);
        }

        $departments = $query->paginate($request->get('per_page', 15));

        return DepartmentResource::collection($departments);
    }

    public function store(DepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        // Validate branch_id is within user's scope
        if (!$request->user()->hasRole('Super Admin')) {
            if ($request->branch_id !== $request->user()->branch_id) {
                abort(403, 'Cannot create department in another branch');
            }
        }

        $department = Department::create($request->validated());

        return new DepartmentResource($department);
    }

    public function show(Request $request, Department $department)
    {
        $this->authorize('view', $department);

        return new DepartmentResource($department);
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        // Validate branch_id if being changed
        if ($request->has('branch_id') && !$request->user()->hasRole('Super Admin')) {
            if ($request->branch_id !== $request->user()->branch_id) {
                abort(403, 'Cannot move department to another branch');
            }
        }

        $department->update($request->validated());

        return new DepartmentResource($department);
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorize('delete', $department);

        $department->update(['is_active' => false]);
        $department->delete();

        return response()->noContent();
    }
}
