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
use App\Http\Controllers\Api\V1\SiteController;
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
    Route::post('/sites/import', [SiteController::class, 'import']);

    // Site Photos
    Route::get('/sites/{site}/photos', [\App\Http\Controllers\Api\V1\PhotoController::class, 'sitePhotos']);
    Route::post('/sites/{site}/photos', [\App\Http\Controllers\Api\V1\PhotoController::class, 'storeSitePhoto']);
    Route::delete('/sites/{site}/photos/{photo}', [\App\Http\Controllers\Api\V1\PhotoController::class, 'deleteSitePhoto']);
    Route::get('/sites/export', [SiteController::class, 'export']);
    Route::get('/sites/summary', [SiteController::class, 'summary']);
    Route::get('/sites/dashboard', [SiteController::class, 'dashboard']);
    Route::apiResource('/sites', SiteController::class);
    Route::get('/sites/{site}/assets', [\App\Http\Controllers\Api\V1\AssetController::class, 'bySite']);
    
    // Assets
    Route::get('/assets/dashboard', [\App\Http\Controllers\Api\V1\AssetController::class, 'dashboard']);
    Route::post('/assets/import', [\App\Http\Controllers\Api\V1\AssetController::class, 'import']);
    Route::get('/assets/export', [\App\Http\Controllers\Api\V1\AssetController::class, 'export']);
    Route::apiResource('/assets', \App\Http\Controllers\Api\V1\AssetController::class);

    // Asset Photos
    Route::get('/assets/{asset}/photos', [\App\Http\Controllers\Api\V1\PhotoController::class, 'assetPhotos']);
    Route::post('/assets/{asset}/photos', [\App\Http\Controllers\Api\V1\PhotoController::class, 'storeAssetPhoto']);
    Route::delete('/assets/{asset}/photos/{photo}', [\App\Http\Controllers\Api\V1\PhotoController::class, 'deleteAssetPhoto']);
    // Asset Lifecycle
    Route::get('/assets/{asset}/lifecycle', [\App\Http\Controllers\Api\V1\AssetLifecycleController::class, 'index']);
    Route::post('/assets/{asset}/lifecycle', [\App\Http\Controllers\Api\V1\AssetLifecycleController::class, 'store']);
    Route::post('/assets/{asset}/transfer', [\App\Http\Controllers\Api\V1\AssetLifecycleController::class, 'transfer']);
    Route::post('/assets/{asset}/retire', [\App\Http\Controllers\Api\V1\AssetLifecycleController::class, 'retire']);
    Route::post('/assets/{asset}/dispose', [\App\Http\Controllers\Api\V1\AssetLifecycleController::class, 'dispose']);

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
    Route::get('/debug/summary', [DebugController::class, 'summary']);
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


    // Assets (Specific routes MUST come before apiResource to avoid implicit binding conflicts)

    // Site Assets (must be inside auth:sanctum group)
    Route::get('/sites/{site}/assets', [\App\Http\Controllers\Api\V1\AssetController::class, 'bySite']);

    // LibreNMS Import
    Route::middleware('auth:sanctum')->prefix('v1/integrations/librenms')->group(function () {
        Route::get('/{integration}/devices', [\App\Http\Controllers\Api\V1\LibreNMSImportController::class, 'devices']);
        Route::get('/{integration}/preview', [\App\Http\Controllers\Api\V1\LibreNMSImportController::class, 'preview']);
        Route::post('/{integration}/import', [\App\Http\Controllers\Api\V1\LibreNMSImportController::class, 'import']);
    });

    // LibreNMS Site Import
    Route::middleware('auth:sanctum')->prefix('v1/integrations/librenms')->group(function () {
        Route::get('/{integration}/locations', [\App\Http\Controllers\Api\V1\LibreNMSSiteController::class, 'locations']);
        Route::get('/{integration}/sites/preview', [\App\Http\Controllers\Api\V1\LibreNMSSiteController::class, 'preview']);
        Route::post('/{integration}/sites/map', [\App\Http\Controllers\Api\V1\LibreNMSSiteController::class, 'map']);
        Route::post('/{integration}/sites/import', [\App\Http\Controllers\Api\V1\LibreNMSSiteController::class, 'import']);
    });

// UISP Import
Route::prefix('v1/integrations/uisp')->middleware('auth:sanctum')->group(function () {
    Route::post('/import/preview', [App\Http\Controllers\Api\V1\UispImportController::class, 'preview']);
    Route::post('/import/analyze', [App\Http\Controllers\Api\V1\UispImportController::class, 'analyzeSingle']);
});

// Generic Import System

// Generic Import System

// Generic Import System

// Generic Import System

// Generic Import System
