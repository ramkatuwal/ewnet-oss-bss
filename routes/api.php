<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\DebugController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/v1/auth/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Profile Management
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/password', [ProfileController::class, 'changePassword']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar']);

    // Organization
    Route::apiResource('/organization/companies', CompanyController::class);
    Route::apiResource('/organization/regions', RegionController::class);
    Route::apiResource('/organization/branches', BranchController::class);
    Route::apiResource('/organization/departments', DepartmentController::class);
    Route::apiResource('/organization/users', UserController::class);

    // Security
    Route::apiResource('/security/roles', RoleController::class);
    Route::get('/security/roles/{role}/users', [RoleController::class, 'users']);
    Route::apiResource('/security/permissions', PermissionController::class);

    // Audit Logs
    Route::get('/security/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/security/audit-logs/{auditLog}', [AuditLogController::class, 'show']);

    // Debug
    Route::middleware(['auth:sanctum', 'can:system.debug.view'])->get('/debug/status', [DebugController::class, 'status']);
    Route::middleware(['auth:sanctum', 'can:system.debug.view'])->get('/debug/logs', [DebugController::class, 'logs']);
});

// Management Scopes
Route::prefix('v1/organization/users/{user}/management-scopes')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\V1\ManagementScopeController::class, 'index']);
    Route::post('/', [App\Http\Controllers\Api\V1\ManagementScopeController::class, 'store']);
    Route::delete('/{scope}', [App\Http\Controllers\Api\V1\ManagementScopeController::class, 'destroy']);
});
