<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user->load('roles.permissions');
            
            // Flatten permissions to an array of strings for easier frontend use
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            $roles = $user->getRoleNames()->toArray();

            Auth::login($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during login'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            Auth::guard('web')->logout();
            
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during logout'
            ], 500);
        }
    }

    public function user(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $user->load('roles.permissions');
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            $roles = $user->getRoleNames()->toArray();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred'
            ], 500);
        }
    }
}
