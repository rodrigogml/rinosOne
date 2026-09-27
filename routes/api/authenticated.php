<?php

use App\Http\Controllers\Api\V1\Access\CurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyCurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyOtherSessionsController;
use App\Http\Controllers\Api\V1\Access\DestroyPasswordController;
use App\Http\Controllers\Api\V1\Access\SetPasswordController;
use App\Http\Controllers\Api\V1\Authorization\AuthorizationAdministrationController;
use App\Http\Controllers\Api\V1\Authorization\ResourceAuthorizationController;
use App\Http\Controllers\Api\V1\Platform\Maintenance\MaintenanceController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use Illuminate\Support\Facades\Route;

Route::put('/auth/password', SetPasswordController::class);
Route::delete('/auth/password', DestroyPasswordController::class);
Route::delete('/auth/session', DestroyCurrentSessionController::class);
Route::delete('/auth/other-sessions', DestroyOtherSessionsController::class);
Route::get('/auth/session', CurrentSessionController::class);

Route::post('/authorization/resource-checks', [ResourceAuthorizationController::class, 'checkBatch']);
Route::get('/authorization/personal-workspace/folders', [ResourceAuthorizationController::class, 'personalWorkspaceFolders']);
Route::prefix('/tenants/{tenantId}/authorization')->whereNumber('tenantId')->group(static function (): void {
    Route::post('/roles', [AuthorizationAdministrationController::class, 'storeRole']);
    Route::post('/roles/{roleId}/permissions', [AuthorizationAdministrationController::class, 'grantPermission'])->whereNumber('roleId');
    Route::post('/roles/{roleId}/assignments', [AuthorizationAdministrationController::class, 'assignRole'])->whereNumber('roleId');
    Route::delete('/roles/{roleId}/assignments/{userId}', [AuthorizationAdministrationController::class, 'removeRoleAssignment'])->whereNumber(['roleId', 'userId']);
    Route::post('/groups', [AuthorizationAdministrationController::class, 'storeGroup']);
    Route::post('/groups/{groupId}/members', [AuthorizationAdministrationController::class, 'addGroupMember'])->whereNumber('groupId');
    Route::delete('/groups/{groupId}/members/{userId}', [AuthorizationAdministrationController::class, 'removeGroupMember'])->whereNumber(['groupId', 'userId']);
    Route::post('/groups/{groupId}/roles', [AuthorizationAdministrationController::class, 'grantGroupRole'])->whereNumber('groupId');
    Route::delete('/groups/{groupId}/roles/{roleId}', [AuthorizationAdministrationController::class, 'removeGroupRole'])->whereNumber(['groupId', 'roleId']);
    Route::post('/restrictions', [AuthorizationAdministrationController::class, 'storeRestriction']);
    Route::post('/restrictions/{restrictionId}/deactivation', [AuthorizationAdministrationController::class, 'deactivateRestriction'])->whereNumber('restrictionId');
    Route::delete('/memberships/{membershipId}', [AuthorizationAdministrationController::class, 'removeMembership'])->whereNumber('membershipId');
    Route::get('/effective-access/users/{userId}', [AuthorizationAdministrationController::class, 'effectiveAccess'])->whereNumber('userId');
    Route::post('/explain/users/{userId}', [AuthorizationAdministrationController::class, 'explain'])->whereNumber('userId');
    Route::get('/audit-events', [AuthorizationAdministrationController::class, 'auditEvents']);
});

Route::get('/profile', [ProfileController::class, 'show']);
Route::patch('/profile', [ProfileController::class, 'update']);
Route::get('/profile/avatar', [ProfileController::class, 'avatar']);
Route::post('/profile/avatar', [ProfileController::class, 'storeAvatar']);
Route::delete('/profile/avatar', [ProfileController::class, 'destroy']);

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
