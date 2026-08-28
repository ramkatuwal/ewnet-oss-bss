<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DepartmentRequest;
use App\Http\Resources\V1\DepartmentResource;
use App\Models\Department;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Department::class);

        $query = Department::with(['branch', 'company']);

        // Apply centralized scope filtering
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), Department::class);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $departments = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return DepartmentResource::collection($departments);
    }

    public function store(DepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        if (!$request->user()->hasRole('Super Admin')) {
            if ($request->branch_id !== $request->user()->branch_id) {
                abort(403, 'Cannot create department in another branch');
            }
        }

        $department = Department::create($request->validated());
        AuditService::log('department.create', 'success', $department);

        return new DepartmentResource($department->load(['branch', 'company']));
    }

    public function show(Request $request, Department $department)
    {
        $this->authorize('view', $department);

        return new DepartmentResource($department->load(['branch', 'company']));
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        if ($request->has('branch_id') && !$request->user()->hasRole('Super Admin')) {
            if ($request->branch_id !== $request->user()->branch_id) {
                abort(403, 'Cannot move department to another branch');
            }
        }

        $department->update($request->validated());
        AuditService::log('department.update', 'success', $department);

        return new DepartmentResource($department->fresh()->load(['branch', 'company']));
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorize('delete', $department);

        $department->delete();
        AuditService::log('department.delete', 'success', $department);

        return response()->json(['message' => 'Department deleted successfully']);
    }
}
