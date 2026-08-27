<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles');
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

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    public function store(UserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $authUser = auth()->user();

        $data['company_id'] = $data['company_id'] ?? $authUser->company_id;
        $data['branch_id'] = $data['branch_id'] ?? $authUser->branch_id;
        $data['department_id'] = $data['department_id'] ?? $authUser->department_id;

        if ($authUser->branch_id && $data['branch_id'] !== $authUser->branch_id) {
            AuditService::log('user.create.attempt', 'failure', null, ['reason' => 'branch_scope_violation']);
            abort(403, 'You can only create users within your branch.');
        }
        if ($authUser->department_id && $data['department_id'] !== $authUser->department_id) {
            AuditService::log('user.create.attempt', 'failure', null, ['reason' => 'department_scope_violation']);
            abort(403, 'You can only create users within your department.');
        }

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            foreach ($roles as $role) {
                if (!$this->canAssignRole($authUser, $role)) {
                    AuditService::log('user.role.assign.attempt', 'failure', $user, ['role_name' => $role->name, 'reason' => 'insufficient_privileges']);
                    abort(403, "Cannot assign role: {$role->name}");
                }
            }
            $user->syncRoles($roles);
            AuditService::log('user.role.assign', 'success', $user, ['roles' => $roles->pluck('name')->toArray()]);
        }

        AuditService::log('user.create', 'success', $user);
        return response()->json($user->load('roles'), 201);
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);
        return response()->json($user->load('roles'));
    }

    public function update(UserRequest $request, User $user)
    {
        // Explicitly check authorization to log boundary violations
        if (!Gate::allows('update', $user)) {
            AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'boundary_violation']);
            abort(403, 'Forbidden');
        }

        $data = $request->validated();
        $authUser = auth()->user();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        if (isset($data['company_id']) && $data['company_id'] !== $authUser->company_id) {
            AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'company_scope_violation']);
            abort(403, 'Cannot move user to another company.');
        }
        if (isset($data['branch_id']) && $authUser->branch_id && $data['branch_id'] !== $authUser->branch_id) {
            AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'branch_scope_violation']);
            abort(403, 'Cannot move user to another branch.');
        }
        if (isset($data['department_id']) && $authUser->department_id && $data['department_id'] !== $authUser->department_id) {
            AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'department_scope_violation']);
            abort(403, 'Cannot move user to another department.');
        }

        $user->update($data);

        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            
            if ($user->id === $authUser->id) {
                $currentUserRoles = $authUser->roles->pluck('name')->toArray();
                $newRoles = $roles->pluck('name')->toArray();
                if (in_array('Super Admin', $newRoles) && !in_array('Super Admin', $currentUserRoles)) {
                    AuditService::log('user.role.assign.attempt', 'failure', $user, ['role_name' => 'Super Admin', 'reason' => 'self_escalation']);
                    abort(403, 'Cannot escalate yourself to Super Admin');
                }
            }

            foreach ($roles as $role) {
                if (!$this->canAssignRole($authUser, $role)) {
                    AuditService::log('user.role.assign.attempt', 'failure', $user, ['role_name' => $role->name, 'reason' => 'insufficient_privileges']);
                    abort(403, "Cannot assign role: {$role->name}");
                }
            }
            $user->syncRoles($roles);
            AuditService::log('user.role.assign', 'success', $user, ['roles' => $roles->pluck('name')->toArray()]);
        }

        AuditService::log('user.update', 'success', $user);
        return response()->json($user->load('roles'));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($user->hasRole('Super Admin') && !$request->user()->hasRole('Super Admin')) {
            AuditService::log('user.delete.attempt', 'failure', $user, ['reason' => 'super_admin_protection']);
            abort(403, 'Cannot delete Super Admin users');
        }

        $user->delete();
        AuditService::log('user.delete', 'success', $user);
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
