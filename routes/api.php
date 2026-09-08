<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AssetLifecycleController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DebugController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\FiberCableController;
use App\Http\Controllers\Api\V1\FiberCoreController;
use App\Http\Controllers\Api\V1\FiberSegmentController;
use App\Http\Controllers\Api\V1\FiberTerminationController;
use App\Http\Controllers\Api\V1\ImportHistoryController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\IntegrationCredentialController;
use App\Http\Controllers\Api\V1\LibreNMSImportController;
use App\Http\Controllers\Api\V1\LibreNMSSiteController;
use App\Http\Controllers\Api\V1\ManagementScopeController;
use App\Http\Controllers\Api\V1\NetworkConnectionPointController;
use App\Http\Controllers\Api\V1\PassiveOpticalPortController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Controllers\Api\V1\PhysicalConnectionController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicBrandingController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\SystemConfigController;
use App\Http\Controllers\Api\V1\SystemInfoController;
use App\Http\Controllers\Api\V1\UispImportController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/v1/auth/login', [AuthController::class, 'login']);

// Authenticated routes

// PUBLIC ROUTES (No Authentication Required)
Route::get('/v1/branding', [PublicBrandingController::class, 'index']);

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
    Route::get('/sites/{site}/photos', [PhotoController::class, 'sitePhotos']);
    Route::post('/sites/{site}/photos', [PhotoController::class, 'storeSitePhoto']);
    Route::delete('/sites/{site}/photos/{photo}', [PhotoController::class, 'deleteSitePhoto']);
    Route::get('/sites/export', [SiteController::class, 'export']);
    Route::get('/sites/summary', [SiteController::class, 'summary']);
    Route::get('/sites/dashboard', [SiteController::class, 'dashboard']);
    Route::apiResource('/sites', SiteController::class);
    Route::get('/sites/{site}/assets', [AssetController::class, 'bySite']);

    // Assets
    Route::get('/assets/dashboard', [AssetController::class, 'dashboard']);
    Route::post('/assets/import', [AssetController::class, 'import']);
    Route::get('/assets/export', [AssetController::class, 'export']);
    Route::apiResource('/assets', AssetController::class);

    // Asset Photos
    Route::get('/assets/{asset}/photos', [PhotoController::class, 'assetPhotos']);
    Route::post('/assets/{asset}/photos', [PhotoController::class, 'storeAssetPhoto']);
    Route::delete('/assets/{asset}/photos/{photo}', [PhotoController::class, 'deleteAssetPhoto']);
    // FIM — Network Connection Points (boundary-point foundation)
    Route::apiResource('/fim/connection-points', NetworkConnectionPointController::class)->parameters([
        'connection-points' => 'networkConnectionPoint',
    ]);

    // FIM — Fiber Cables (backend-only spatial foundation)
    Route::apiResource('/fim/fiber-cables', FiberCableController::class)->parameters([
        'fiber-cables' => 'fiberCable',
    ]);
    Route::apiResource('/fim/fiber-segments', FiberSegmentController::class)->parameters([
        'fiber-segments' => 'fiberSegment',
    ]);
    Route::get('/fim/fiber-segments/{fiberSegment}/cores', [FiberCoreController::class, 'segmentCores']);
    Route::post('/fim/fiber-segments/{fiberSegment}/cores/generate', [FiberCoreController::class, 'generate']);
    Route::apiResource('/fim/fiber-cores', FiberCoreController::class)->parameters([
        'fiber-cores' => 'fiberCore',
    ]);
    Route::get('/fim/fiber-cores/{fiberCore}/terminations', [FiberTerminationController::class, 'index']);
    Route::post('/fim/fiber-cores/{fiberCore}/terminations', [FiberTerminationController::class, 'store']);
    Route::apiResource('/fim/fiber-terminations', FiberTerminationController::class)->only(['show', 'update', 'destroy'])->parameters([
        'fiber-terminations' => 'fiberTermination',
    ]);
    Route::apiResource('/fim/physical-connections', PhysicalConnectionController::class)->parameters(['physical-connections' => 'physicalConnection']);
    Route::get('/assets/{asset}/passive-optical-ports', [PassiveOpticalPortController::class, 'index']);
    Route::post('/assets/{asset}/passive-optical-ports', [PassiveOpticalPortController::class, 'store']);
    Route::apiResource('/fim/passive-optical-ports', PassiveOpticalPortController::class)->only(['show', 'update', 'destroy'])->parameters([
        'passive-optical-ports' => 'passiveOpticalPort',
    ]);

    // Asset Lifecycle
    Route::get('/assets/{asset}/lifecycle', [AssetLifecycleController::class, 'index']);
    Route::post('/assets/{asset}/lifecycle', [AssetLifecycleController::class, 'store']);
    Route::post('/assets/{asset}/transfer', [AssetLifecycleController::class, 'transfer']);
    Route::post('/assets/{asset}/retire', [AssetLifecycleController::class, 'retire']);
    Route::post('/assets/{asset}/dispose', [AssetLifecycleController::class, 'dispose']);

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
    Route::get('/', [ManagementScopeController::class, 'index']);
    Route::post('/', [ManagementScopeController::class, 'store']);
    Route::delete('/{scope}', [ManagementScopeController::class, 'destroy']);
});

// System Info & Configuration
Route::middleware('auth:sanctum')->prefix('v1/system')->group(function () {
    Route::get('/info', [SystemInfoController::class, 'index']);
    Route::get('/configuration', [SystemConfigController::class, 'index']);
    Route::put('/configuration', [SystemConfigController::class, 'update']);
    Route::post('/branding', [SystemConfigController::class, 'uploadBranding']);
});

// Integrations
Route::middleware('auth:sanctum')->prefix('v1/integrations')->group(function () {
    Route::get('/', [IntegrationController::class, 'index']);
    Route::post('/', [IntegrationController::class, 'store']);
    Route::get('/{integration}', [IntegrationController::class, 'show']);
    Route::put('/{integration}', [IntegrationController::class, 'update']);
    Route::delete('/{integration}', [IntegrationController::class, 'destroy']);
    Route::get('/{integration}/stats', [IntegrationController::class, 'stats']);
    Route::get('/{integration}/audit-logs', [IntegrationController::class, 'auditLogs']);
    Route::post('/{integration}/test', [IntegrationController::class, 'testConnection']);
    Route::post('/{integration}/health-check', [IntegrationController::class, 'healthCheck']);
    Route::post('/{integration}/sync', [IntegrationController::class, 'sync']);
    Route::get('/{integration}/syncs', [IntegrationController::class, 'syncs']);

    // Credentials (nested under integration)
    Route::get('/{integration}/credentials', [IntegrationCredentialController::class, 'index']);
    Route::post('/{integration}/credentials', [IntegrationCredentialController::class, 'store']);
    Route::post('/{integration}/credentials/{credential}/rotate', [IntegrationCredentialController::class, 'rotate']);
    Route::delete('/{integration}/credentials/{credential}', [IntegrationCredentialController::class, 'destroy']);

    // Generic import (canonical preview + execute)
    Route::post('/{integration}/import/preview', [IntegrationController::class, 'importPreview']);
    Route::post('/{integration}/import', [IntegrationController::class, 'import']);

    // UISP provider-specific import routes
    Route::post('/{integration}/uisp/import/preview', [UispImportController::class, 'preview']);
    Route::post('/{integration}/uisp/import/analyze', [UispImportController::class, 'analyzeSingle']);
    Route::post('/{integration}/uisp/import/execute', [UispImportController::class, 'execute']);

    // LibreNMS legacy import routes (kept for backward compatibility)
    Route::get('/librenms/{integration}/devices', [LibreNMSImportController::class, 'devices']);
    Route::get('/librenms/{integration}/preview', [LibreNMSImportController::class, 'preview']);
    Route::post('/librenms/{integration}/import', [LibreNMSImportController::class, 'import']);
    Route::get('/librenms/{integration}/locations', [LibreNMSSiteController::class, 'locations']);
    Route::get('/librenms/{integration}/sites/preview', [LibreNMSSiteController::class, 'preview']);
    Route::post('/librenms/{integration}/sites/map', [LibreNMSSiteController::class, 'map']);
    Route::post('/librenms/{integration}/sites/import', [LibreNMSSiteController::class, 'import']);
});

// Import history
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/import/history', [ImportHistoryController::class, 'index']);
});
