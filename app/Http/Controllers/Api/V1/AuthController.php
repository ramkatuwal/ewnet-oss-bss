<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            AuditService::log('auth.login.failure', 'failure', null, [
                'email' => $credentials['email'],
            ]);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            AuditService::log('auth.login.failure', 'failure', $user, ['reason' => 'account_inactive']);
            return response()->json(['message' => 'Account is deactivated'], 403);
        }

        $user->update([
            'failed_login_attempts' => 0,
            'locked_at' => null,
            'last_login_at' => now(),
        ]);

        try { $request->session()->regenerate(); } catch (\RuntimeException $e) { /* Session not available */ }

        AuditService::log('auth.login.success', 'success', $user);

        // Return user data for frontend hydration
        $user->load(['roles.permissions', 'company', 'branch.region.company', 'department.branch']);

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            AuditService::log('auth.logout', 'success', $user);
        }

        Auth::guard('web')->logout();

        // Safely handle session invalidation (may fail in stateless/test contexts)
        try {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\RuntimeException $e) {
            // Session not available (API-only or test context)
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->load(['roles.permissions', 'company', 'branch.region.company', 'department.branch']);
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'avatar' => $user->avatar,
                'is_active' => $user->is_active,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'department_id' => $user->department_id,
                'company' => $user->company ? ['id' => $user->company->id, 'name' => $user->company->name] : null,
                'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name] : null,
                'department' => $user->department ? ['id' => $user->department->id, 'name' => $user->department->name] : null,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
        ]);
    }
}
