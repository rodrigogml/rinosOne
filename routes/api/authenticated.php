<?php

use App\Http\Controllers\Api\V1\Access\CurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyCurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyOtherSessionsController;
use App\Http\Controllers\Api\V1\Access\DestroyPasswordController;
use App\Http\Controllers\Api\V1\Access\SetPasswordController;
use App\Http\Controllers\Api\V1\Platform\Maintenance\MaintenanceController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use Illuminate\Support\Facades\Route;

Route::put('/auth/password', SetPasswordController::class);
Route::delete('/auth/password', DestroyPasswordController::class);
Route::delete('/auth/session', DestroyCurrentSessionController::class);
Route::delete('/auth/other-sessions', DestroyOtherSessionsController::class);
Route::get('/auth/session', CurrentSessionController::class);

Route::get('/tenants', [TenantController::class, 'index']);
Route::post('/tenants', [TenantController::class, 'store']);
Route::post('/tenants/{tenantId}/contexts', [TenantController::class, 'startContext'])->whereNumber('tenantId');
Route::delete('/tenants/{tenantId}/contexts', [TenantController::class, 'endContext'])->whereNumber('tenantId');
Route::post('/tenants/{tenantId}/availability', [TenantController::class, 'changeAvailability'])->whereNumber('tenantId');

Route::prefix('/platform/maintenance')->group(static function (): void {
    Route::get('/routines', [MaintenanceController::class, 'index']);
    Route::get('/routines/{routineKey}', [MaintenanceController::class, 'show']);
    Route::get('/audit', [MaintenanceController::class, 'audit']);
    Route::post('/routines/{routineKey}/actions/{action}', [MaintenanceController::class, 'synchronize']);
});
