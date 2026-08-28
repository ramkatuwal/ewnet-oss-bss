<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class ManagementScopeController extends Controller
{
    /**
     * List scopes for a user.
     */
    public function index(Request $request, User $user)
    {
        $this->authorize('view', $user);

        return response()->json(
            $user->managementScopes->map(fn(UserManagementScope $s) => [
                'id' => $s->id,
                'scope_type' => $s->scope_type,
                'scope_id' => $s->scope_id,
                'scope_name' => $s->scope_name,
                'granted_by' => $s->grantor?->name,
                'granted_at' => $s->granted_at,
            ])
        );
    }

    /**
     * Assign a management scope to a user.
     */
    public function store(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'scope_type' => 'required|string|in:' . implode(',', UserManagementScope::SCOPE_TYPES),
            'scope_id' => 'required|integer|min:1',
        ]);

        $actor = $request->user();

        // Validate scope entity exists
        if (!UserManagementScope::validateScope($validated['scope_type'], $validated['scope_id'])) {
            abort(422, "Invalid scope: {$validated['scope_type']} #{$validated['scope_id']} does not exist.");
        }

        // Check actor can grant this scope
        if (!ManagementScopeService::canGrantScope($actor, $validated['scope_type'], $validated['scope_id'])) {
            AuditService::log('scope.assign.attempt', 'failure', $user, [
                'scope_type' => $validated['scope_type'],
                'scope_id' => $validated['scope_id'],
                'reason' => 'insufficient_authority',
            ]);
            abort(403, 'You cannot grant a scope outside your own authority boundary.');
        }

        // Prevent Super Admin scope assignment via scope system
        // Super Admin is a role, not a scope

        // Create or find existing
        $scope = UserManagementScope::firstOrCreate(
            [
                'user_id' => $user->id,
                'scope_type' => $validated['scope_type'],
                'scope_id' => $validated['scope_id'],
            ],
            [
                'granted_by' => $actor->id,
                'granted_at' => now(),
            ]
        );

        AuditService::log('scope.assign', 'success', $user, [
            'scope_type' => $validated['scope_type'],
            'scope_id' => $validated['scope_id'],
            'granted_by' => $actor->id,
        ]);

        return response()->json([
            'message' => 'Scope assigned successfully',
            'scope' => [
                'id' => $scope->id,
                'scope_type' => $scope->scope_type,
                'scope_id' => $scope->scope_id,
                'scope_name' => $scope->scope_name,
            ],
        ], 201);
    }

    /**
     * Revoke a management scope from a user.
     */
    public function destroy(Request $request, User $user, UserManagementScope $scope)
    {
        $this->authorize('update', $user);

        $actor = $request->user();

        // Verify scope belongs to this user
        if ($scope->user_id !== $user->id) {
            abort(403, 'Scope does not belong to this user.');
        }

        // Check actor can revoke this scope (same rules as granting)
        if (!ManagementScopeService::canGrantScope($actor, $scope->scope_type, $scope->scope_id)) {
            AuditService::log('scope.revoke.attempt', 'failure', $user, [
                'scope_type' => $scope->scope_type,
                'scope_id' => $scope->scope_id,
                'reason' => 'insufficient_authority',
            ]);
            abort(403, 'You cannot revoke a scope outside your own authority boundary.');
        }

        $scopeData = ['scope_type' => $scope->scope_type, 'scope_id' => $scope->scope_id];
        $scope->delete();

        AuditService::log('scope.revoke', 'success', $user, array_merge($scopeData, [
            'revoked_by' => $actor->id,
        ]));

        return response()->json(['message' => 'Scope revoked successfully']);
    }
}
