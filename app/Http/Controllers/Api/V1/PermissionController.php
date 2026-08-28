<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PermissionResource;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Permission::class);

        $query = Permission::withCount('roles');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($request->filled('domain')) {
            $domain = $request->get('domain');
            $query->where('name', 'like', "{$domain}.%");
        }

        $permissions = $query->orderBy('name')->paginate($request->get('per_page', 50));

        return PermissionResource::collection($permissions);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Permission::class);
        $authUser = $request->user();

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $request->name, 'guard_name' => 'web']);

        AuditService::log('permission.create', 'success', null, [
            'actor_id' => $authUser->id,
            'permission_name' => $permission->name,
        ]);

        return new PermissionResource($permission->loadCount('roles'));
    }

    public function show(Request $request, Permission $permission)
    {
        $this->authorize('view', $permission);

        return new PermissionResource($permission->loadCount('roles'));
    }

    public function update(Request $request, Permission $permission)
    {
        $this->authorize('update', $permission);
        $authUser = $request->user();

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
        ]);

        $oldName = $permission->name;
        $permission->update(['name' => $request->name]);

        AuditService::log('permission.update', 'success', null, [
            'actor_id' => $authUser->id,
            'old_name' => $oldName,
            'new_name' => $permission->name,
        ]);

        return new PermissionResource($permission->fresh()->loadCount('roles'));
    }

    public function destroy(Request $request, Permission $permission)
    {
        $this->authorize('delete', $permission);
        $authUser = $request->user();

        $permName = $permission->name;
        $permission->delete();

        AuditService::log('permission.delete', 'success', null, [
            'actor_id' => $authUser->id,
            'deleted_permission' => $permName,
        ]);

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
