<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use App\Http\Requests\Api\V1\UpdateRoleRequest;
use App\Http\Resources\V1\RoleResource;
use App\Http\Resources\V1\UserResource;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::with('permissions')->addSelect(['*'])
            ->selectRaw('(SELECT COUNT(*) FROM model_has_roles WHERE role_id = roles.id) as users_count');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'ilike', "%{$search}%");
        }

        $roles = $query->orderBy('name')->paginate($request->get('per_page', 25));

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request)
    {
        $data = $request->validated();
        $authUser = $request->user();

        // Authorization: Prevent non-Super Admin from creating Super Admin role
        if ($data['name'] === 'Super Admin' && !$authUser->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can create Super Admin role');
        }

        // Authorization: Prevent assigning permissions the actor doesn't possess
        if (isset($data['permissions']) && !$authUser->hasRole('Super Admin')) {
            $perms = Permission::whereIn('id', $data['permissions'])->pluck('name');
            foreach ($perms as $permName) {
                if (!$authUser->hasPermissionTo($permName)) {
                    abort(403, "Cannot assign permission '{$permName}' you do not possess");
                }
            }
        }

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (isset($data['permissions'])) {
            $perms = Permission::whereIn('id', $data['permissions'])->get();
            $role->syncPermissions($perms);
        }

        AuditService::log('role.create', 'success', $role, [
            'actor_id' => $authUser->id,
            'permissions_count' => $role->permissions->count(),
        ]);

        $role->load('permissions');
        $role->users_count = \DB::table('model_has_roles')->where('role_id', $role->id)->count();
        return new RoleResource($role);
    }

    public function show(Request $request, Role $role)
    {
        $this->authorize('view', $role);

        $role->load('permissions');
        $role->users_count = \DB::table('model_has_roles')->where('role_id', $role->id)->count();
        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $data = $request->validated();
        $authUser = $request->user();

        // Authorization: Prevent non-Super Admin from renaming to Super Admin
        if (isset($data['name']) && $data['name'] === 'Super Admin' && !$authUser->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can rename to Super Admin');
        }

        // Authorization: Prevent assigning permissions the actor doesn't possess
        if (isset($data['permissions']) && !$authUser->hasRole('Super Admin')) {
            $perms = Permission::whereIn('id', $data['permissions'])->pluck('name');
            foreach ($perms as $permName) {
                if (!$authUser->hasPermissionTo($permName)) {
                    abort(403, "Cannot assign permission '{$permName}' you do not possess");
                }
            }
        }

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $perms = Permission::whereIn('id', $data['permissions'])->get();
            $role->syncPermissions($perms);
        }

        AuditService::log('role.update', 'success', $role, [
            'actor_id' => $authUser->id,
            'permissions_count' => $role->permissions->count(),
        ]);

        $role = $role->fresh();
        $role->load('permissions');
        $role->users_count = \DB::table('model_has_roles')->where('role_id', $role->id)->count();
        return new RoleResource($role);
    }

    public function destroy(Request $request, Role $role)
    {
        $this->authorize('delete', $role);
        $authUser = $request->user();

        $roleName = $role->name;
        $role->delete();

        AuditService::log('role.delete', 'success', null, [
            'actor_id' => $authUser->id,
            'deleted_role' => $roleName,
        ]);

        return response()->json(['message' => 'Role deleted successfully']);
    }

    /**
     * Get users assigned to this role, scoped by actor's management scope.
     */
    public function users(Request $request, Role $role)
    {
        $this->authorize('view', $role);
        $authUser = $request->user();

        // Use raw query instead of Spatie's users() relationship to avoid config issues
        $userIds = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id');
        
        $query = \App\Models\User::whereIn('id', $userIds)
            ->with(['company', 'branch.region', 'department', 'managementScopes']);

        // Apply management scope filtering for non-Super Admin
        if (!ManagementScopeService::hasGlobalScope($authUser)) {
            $query = ManagementScopeService::applyScopeToQuery($query, $authUser, \App\Models\User::class);
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return UserResource::collection($users);
    }
}
