<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Role::class);
        return response()->json(Role::with('permissions')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Role::class);
        
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // CRITICAL: Prevent non-Super Admins from creating Super Admin role
        if ($request->name === 'Super Admin' && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can create Super Admin role');
        }

        // CRITICAL: Validate user can only assign permissions they have
        if ($request->has('permissions')) {
            $requestedPermissions = Permission::whereIn('id', $request->permissions)->get();
            foreach ($requestedPermissions as $perm) {
                if (!auth()->user()->hasPermissionTo($perm->name)) {
                    abort(403, 'Cannot assign permissions you do not possess');
                }
            }
        }

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        if ($request->has('permissions')) {
            $role->syncPermissions($requestedPermissions);
        }

        return response()->json($role->load('permissions'), 201);
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);
        
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // CRITICAL: Prevent non-Super Admins from renaming to Super Admin
        if ($request->name === 'Super Admin' && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can create Super Admin role');
        }

        // CRITICAL: Validate user can only assign permissions they have
        if ($request->has('permissions')) {
            $requestedPermissions = Permission::whereIn('id', $request->permissions)->get();
            foreach ($requestedPermissions as $perm) {
                if (!auth()->user()->hasPermissionTo($perm->name)) {
                    abort(403, 'Cannot assign permissions you do not possess');
                }
            }
        }

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($requestedPermissions);
        }

        return response()->json($role->load('permissions'));
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }
}
