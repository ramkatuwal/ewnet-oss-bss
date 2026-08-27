<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserRequest;
use App\Models\User;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles');

        // Authorization-aware scoped query
        $authUser = $request->user();
        if (!$authUser->hasRole('Super Admin')) {
            if ($authUser->company_id && !$authUser->branch_id) {
                $query->where('company_id', $authUser->company_id);
            } elseif ($authUser->branch_id && !$authUser->department_id) {
                $query->where('branch_id', $authUser->branch_id);
            } elseif ($authUser->department_id) {
                $query->where('department_id', $authUser->department_id);
            } else {
                $query->where('id', $authUser->id);
            }
        }

        $users = $query->paginate($request->get('per_page', 15));
        return response()->json($users);
    }

    public function store(UserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $authUser = auth()->user();

        // Derive organizational ownership from authenticated actor if not provided
        $data['company_id'] = $data['company_id'] ?? $authUser->company_id;
        $data['branch_id'] = $data['branch_id'] ?? $authUser->branch_id;
        $data['department_id'] = $data['department_id'] ?? $authUser->department_id;

        // Enforce actor's scope limits (Defense in Depth)
        if ($authUser->branch_id && $data['branch_id'] !== $authUser->branch_id) {
            abort(403, 'You can only create users within your branch.');
        }
        if ($authUser->department_id && $data['department_id'] !== $authUser->department_id) {
            abort(403, 'You can only create users within your department.');
        }

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            
            foreach ($roles as $role) {
                if (!$this->canAssignRole($authUser, $role)) {
                    abort(403, "Cannot assign role: {$role->name}");
                }
            }
            $user->syncRoles($roles);
        }

        return response()->json($user->load('roles'), 201);
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);
        return response()->json($user->load('roles'));
    }

    public function update(UserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $authUser = auth()->user();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Prevent moving user outside actor's scope
        if (isset($data['company_id']) && $data['company_id'] !== $authUser->company_id) {
            abort(403, 'Cannot move user to another company.');
        }
        if (isset($data['branch_id']) && $authUser->branch_id && $data['branch_id'] !== $authUser->branch_id) {
            abort(403, 'Cannot move user to another branch.');
        }
        if (isset($data['department_id']) && $authUser->department_id && $data['department_id'] !== $authUser->department_id) {
            abort(403, 'Cannot move user to another department.');
        }

        $user->update($data);

        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            
            // Prevent self-escalation
            if ($user->id === $authUser->id) {
                $currentUserRoles = $authUser->roles->pluck('name')->toArray();
                $newRoles = $roles->pluck('name')->toArray();
                if (in_array('Super Admin', $newRoles) && !in_array('Super Admin', $currentUserRoles)) {
                    abort(403, 'Cannot escalate yourself to Super Admin');
                }
            }

            foreach ($roles as $role) {
                if (!$this->canAssignRole($authUser, $role)) {
                    abort(403, "Cannot assign role: {$role->name}");
                }
            }
            $user->syncRoles($roles);
        }

        return response()->json($user->load('roles'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($user->hasRole('Super Admin') && !$request->user()->hasRole('Super Admin')) {
            abort(403, 'Cannot delete Super Admin users');
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    protected function canAssignRole($currentUser, $targetRole): bool
    {
        if ($targetRole->name === 'Super Admin') {
            return $currentUser->hasRole('Super Admin');
        }
        return $currentUser->hasPermissionTo('users.update');
    }
}
