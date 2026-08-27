<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with(['roles', 'company', 'branch.region', 'department']);
        $authUser = $request->user();

        // Organizational scoping
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

        // Filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->get('company_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->get('role')));
        }

        $users = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(UserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $authUser = auth()->user();

        $data['company_id'] = $data['company_id'] ?? $authUser->company_id;
        $data['branch_id'] = $data['branch_id'] ?? $authUser->branch_id;
        $data['department_id'] = $data['department_id'] ?? $authUser->department_id;

        // Scope validation
        if (!$authUser->hasRole('Super Admin')) {
            if ($authUser->company_id && isset($data['company_id']) && $data['company_id'] != $authUser->company_id) {
                AuditService::log('user.create.attempt', 'failure', null, ['reason' => 'company_scope_violation']);
                abort(403, 'Cannot create user in another company.');
            }
            if ($authUser->branch_id && isset($data['branch_id']) && $data['branch_id'] != $authUser->branch_id) {
                AuditService::log('user.create.attempt', 'failure', null, ['reason' => 'branch_scope_violation']);
                abort(403, 'Can only create users within your branch.');
            }
        }

        $data['password'] = Hash::make($data['password']);
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create($data);

        // Role assignment with privilege check
        if (!empty($roles)) {
            $roleModels = Role::whereIn('id', $roles)->get();
            foreach ($roleModels as $role) {
                if (!$this->canAssignRole($authUser, $role)) {
                    AuditService::log('user.role.assign.attempt', 'failure', $user, ['role_name' => $role->name, 'reason' => 'insufficient_privileges']);
                    abort(403, "Cannot assign role: {$role->name}");
                }
            }
            $user->syncRoles($roleModels);
            AuditService::log('user.role.assign', 'success', $user, ['roles' => $roleModels->pluck('name')->toArray()]);
        }

        AuditService::log('user.create', 'success', $user);

        return new UserResource($user->load(['roles', 'company', 'branch.region', 'department']));
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);
        return new UserResource($user->load(['roles', 'company', 'branch.region', 'department']));
    }

    public function update(UserRequest $request, User $user)
    {
        if (!Gate::allows('update', $user)) {
            AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'boundary_violation']);
            abort(403, 'Forbidden');
        }

        $data = $request->validated();
        $authUser = auth()->user();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Scope validation
        if (!$authUser->hasRole('Super Admin')) {
            if (isset($data['company_id']) && $data['company_id'] != $authUser->company_id) {
                AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'company_scope_violation']);
                abort(403, 'Cannot move user to another company.');
            }
            if ($authUser->branch_id && isset($data['branch_id']) && $data['branch_id'] != $authUser->branch_id) {
                AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'branch_scope_violation']);
                abort(403, 'Cannot move user to another branch.');
            }
        }

        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $user->update($data);

        // Role assignment
        if ($roles !== null) {
            $roleModels = Role::whereIn('id', $roles)->get();

            // Self-escalation prevention
            if ($user->id === $authUser->id) {
                $currentRoles = $authUser->getRoleNames()->toArray();
                $newRoles = $roleModels->pluck('name')->toArray();
                if (in_array('Super Admin', $newRoles) && !in_array('Super Admin', $currentRoles)) {
                    AuditService::log('user.role.assign.attempt', 'failure', $user, ['role_name' => 'Super Admin', 'reason' => 'self_escalation']);
                    abort(403, 'Cannot escalate yourself to Super Admin');
                }
            }

            foreach ($roleModels as $role) {
                if (!$this->canAssignRole($authUser, $role)) {
                    AuditService::log('user.role.assign.attempt', 'failure', $user, ['role_name' => $role->name, 'reason' => 'insufficient_privileges']);
                    abort(403, "Cannot assign role: {$role->name}");
                }
            }
            $user->syncRoles($roleModels);
            AuditService::log('user.role.assign', 'success', $user, ['roles' => $roleModels->pluck('name')->toArray()]);
        }

        AuditService::log('user.update', 'success', $user);

        return new UserResource($user->fresh()->load(['roles', 'company', 'branch.region', 'department']));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($user->hasRole('Super Admin') && !$request->user()->hasRole('Super Admin')) {
            AuditService::log('user.delete.attempt', 'failure', $user, ['reason' => 'super_admin_protection']);
            abort(403, 'Cannot delete Super Admin users');
        }

        if ($user->id === $request->user()->id) {
            abort(403, 'Cannot delete your own account');
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
