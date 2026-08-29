<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\DashboardController;
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

// PUBLIC ROUTES (No Authentication Required)
Route::get('/v1/branding', [\App\Http\Controllers\Api\V1\PublicBrandingController::class, 'index']);

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

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

// System Info & Configuration
Route::middleware('auth:sanctum')->prefix('v1/system')->group(function () {
    Route::get('/info', [App\Http\Controllers\Api\V1\SystemInfoController::class, 'index']);
    Route::get('/configuration', [App\Http\Controllers\Api\V1\SystemConfigController::class, 'index']);
    Route::put('/configuration', [App\Http\Controllers\Api\V1\SystemConfigController::class, 'update']);
});

// Integrations
Route::middleware('auth:sanctum')->prefix('v1/integrations')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\V1\IntegrationController::class, 'index']);
    Route::post('/', [App\Http\Controllers\Api\V1\IntegrationController::class, 'store']);
    Route::get('/{integration}', [App\Http\Controllers\Api\V1\IntegrationController::class, 'show']);
    Route::put('/{integration}', [App\Http\Controllers\Api\V1\IntegrationController::class, 'update']);
    Route::delete('/{integration}', [App\Http\Controllers\Api\V1\IntegrationController::class, 'destroy']);
    Route::post('/{integration}/test', [App\Http\Controllers\Api\V1\IntegrationController::class, 'testConnection']);
    Route::post('/{integration}/health-check', [App\Http\Controllers\Api\V1\IntegrationController::class, 'healthCheck']);
    Route::post('/{integration}/sync', [App\Http\Controllers\Api\V1\IntegrationController::class, 'sync']);
    Route::get('/{integration}/syncs', [App\Http\Controllers\Api\V1\IntegrationController::class, 'syncs']);

    // Credentials (nested under integration)
    Route::get('/{integration}/credentials', [App\Http\Controllers\Api\V1\IntegrationCredentialController::class, 'index']);
    Route::post('/{integration}/credentials', [App\Http\Controllers\Api\V1\IntegrationCredentialController::class, 'store']);
    Route::delete('/{integration}/credentials/{credential}', [App\Http\Controllers\Api\V1\IntegrationCredentialController::class, 'destroy']);
});
