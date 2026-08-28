<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
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

        // Apply centralized scope filtering
        $query = ManagementScopeService::applyScopeToQuery($query, $authUser, User::class);

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

        // Scope validation via centralized service
        if (!ManagementScopeService::hasGlobalScope($authUser)) {
            $tempUser = new User($data);
            if (!ManagementScopeService::isInScope($authUser, $tempUser)) {
                AuditService::log('user.create.attempt', 'failure', null, ['reason' => 'scope_violation']);
                abort(403, 'Cannot create user outside your management scope.');
            }
        }

        $data['password'] = Hash::make($data['password']);
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create($data);

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

        // Handle management scopes
        if ($request->has('management_scopes')) {
            $scopes = $request->input('management_scopes', []);
            foreach ($scopes as $scopeData) {
                if (isset($scopeData['scope_type'], $scopeData['scope_id'])) {
                    UserManagementScope::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'scope_type' => $scopeData['scope_type'],
                            'scope_id' => $scopeData['scope_id'],
                        ],
                        [
                            'granted_by' => $authUser->id,
                            'granted_at' => now(),
                        ]
                    );
                    AuditService::log('scope.assign', 'success', $user, [
                        'scope_type' => $scopeData['scope_type'],
                        'scope_id' => $scopeData['scope_id'],
                        'granted_by' => $authUser->id,
                    ]);
                }
            }
        }



        return new UserResource($user->load(['roles', 'company', 'branch.region', 'department']));
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);
        return new UserResource($user->load(['roles', 'company', 'branch.region', 'department', 'managementScopes']));
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

        // Scope validation for org changes
        if (!ManagementScopeService::hasGlobalScope($authUser)) {
            if (isset($data['company_id']) || isset($data['branch_id']) || isset($data['department_id'])) {
                $tempUser = clone $user;
                $tempUser->fill(array_intersect_key($data, array_flip(['company_id', 'branch_id', 'department_id'])));
                if (!ManagementScopeService::isInScope($authUser, $tempUser)) {
                    AuditService::log('user.update.attempt', 'failure', $user, ['reason' => 'scope_violation']);
                    abort(403, 'Cannot move user outside your management scope.');
                }
            }
        }

        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        $user->update($data);

        if ($roles !== null) {
            $roleModels = Role::whereIn('id', $roles)->get();

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

        
        // Handle management scopes if provided
        if ($request->has('management_scopes')) {
            $scopes = $request->input('management_scopes', []);
            
            // Remove scopes not in the new list
            $existingScopes = $user->managementScopes;
            foreach ($existingScopes as $existing) {
                $stillExists = collect($scopes)->contains(function ($s) use ($existing) {
                    return $s['scope_type'] === $existing->scope_type && $s['scope_id'] == $existing->scope_id;
                });
                if (!$stillExists) {
                    $existing->delete();
                    AuditService::log('scope.revoke', 'success', $user, [
                        'scope_type' => $existing->scope_type,
                        'scope_id' => $existing->scope_id,
                        'revoked_by' => $authUser->id,
                    ]);
                }
            }
            
            // Add new scopes
            foreach ($scopes as $scopeData) {
                if (isset($scopeData['scope_type'], $scopeData['scope_id'])) {
                    UserManagementScope::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'scope_type' => $scopeData['scope_type'],
                            'scope_id' => $scopeData['scope_id'],
                        ],
                        [
                            'granted_by' => $authUser->id,
                            'granted_at' => now(),
                        ]
                    );
                    AuditService::log('scope.assign', 'success', $user, [
                        'scope_type' => $scopeData['scope_type'],
                        'scope_id' => $scopeData['scope_id'],
                        'granted_by' => $authUser->id,
                    ]);
                }
            }
        }

        AuditService::log('user.update', 'success', $user);

        return new UserResource($user->fresh()->load(['roles', 'company', 'branch.region', 'department', 'managementScopes']));
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
