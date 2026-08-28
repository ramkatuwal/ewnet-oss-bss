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

        $query = Department::with(['branch.region.company', 'company'])->withCount('users');

        // Apply centralized scope filtering
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), Department::class);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $departments = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return DepartmentResource::collection($departments);
    }

    public function store(DepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $data = $request->validated();
        $authUser = $request->user();

        // Verify actor has authority over the target branch using centralized scope
        if (!ManagementScopeService::hasGlobalScope($authUser)) {
            // Ensure company_id is set for scope resolution
            if (!isset($data['company_id']) && isset($data['branch_id'])) {
                $branch = \App\Models\Branch::with('region')->find($data['branch_id']);
                if ($branch && $branch->region) {
                    $data['company_id'] = $branch->region->company_id;
                }
            }
            $tempDept = new Department($data);
            if (!ManagementScopeService::isInScope($authUser, $tempDept)) {
                AuditService::log('department.create.attempt', 'failure', null, [
                    'reason' => 'scope_violation',
                    'branch_id' => $data['branch_id'] ?? null,
                ]);
                abort(403, 'Cannot create department outside your management scope.');
            }
        }

        $department = Department::create($data);
        AuditService::log('department.create', 'success', $department);

        return new DepartmentResource(
            $department->load(['branch.region.company', 'company'])->loadCount('users')
        );
    }

    public function show(Request $request, Department $department)
    {
        $this->authorize('view', $department);

        return new DepartmentResource(
            $department->load(['branch.region.company', 'company'])->loadCount('users')
        );
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $data = $request->validated();
        $authUser = $request->user();

        // If changing branch_id, verify authority over BOTH old and new parent
        if (!ManagementScopeService::hasGlobalScope($authUser)) {
            if (isset($data['branch_id']) && $data['branch_id'] != $department->branch_id) {
                $tempNewDept = new Department(['branch_id' => $data['branch_id']]);
                if (!ManagementScopeService::isInScope($authUser, $tempNewDept)) {
                    AuditService::log('department.update.attempt', 'failure', $department, [
                        'reason' => 'branch_move_scope_violation',
                        'old_branch_id' => $department->branch_id,
                        'new_branch_id' => $data['branch_id'],
                    ]);
                    abort(403, 'Cannot move department to a branch outside your management scope.');
                }
            }
        }

        $department->update($data);
        AuditService::log('department.update', 'success', $department);

        return new DepartmentResource(
            $department->fresh()->load(['branch.region.company', 'company'])->loadCount('users')
        );
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorize('delete', $department);

        // Check for dependent users before deletion
        $userCount = $department->users()->count();
        if ($userCount > 0) {
            abort(422, "Cannot delete department with {$userCount} assigned user(s). Reassign users first.");
        }

        $department->delete();
        AuditService::log('department.delete', 'success', $department);

        return response()->json(['message' => 'Department deleted successfully']);
    }
}
