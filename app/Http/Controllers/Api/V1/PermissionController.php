<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Permission::class);
        return response()->json(Permission::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Permission::class);
        $request->validate(['name' => 'required|string|unique:permissions,name']);
        $permission = Permission::create(['name' => $request->name, 'guard_name' => 'web']);
        return response()->json($permission, 201);
    }

    public function show(Permission $permission)
    {
        $this->authorize('view', $permission);
        return response()->json($permission);
    }

    public function update(Request $request, Permission $permission)
    {
        $this->authorize('update', $permission);
        $request->validate(['name' => 'required|string|unique:permissions,name,' . $permission->id]);
        $permission->update(['name' => $request->name]);
        return response()->json($permission);
    }

    public function destroy(Permission $permission)
    {
        $this->authorize('delete', $permission);
        $permission->delete();
        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
