<?php

use App\Http\Controllers\Api\V1\Access\CurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyCurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyOtherSessionsController;
use App\Http\Controllers\Api\V1\Access\DestroyPasswordController;
use App\Http\Controllers\Api\V1\Access\SetPasswordController;
use App\Http\Controllers\Api\V1\Authorization\AdvancedAuthorizationAdministrationController;
use App\Http\Controllers\Api\V1\Authorization\AuthorizationAdministrationController;
use App\Http\Controllers\Api\V1\Authorization\ResourceAuthorizationController;
use App\Http\Controllers\Api\V1\Drive\DriveWorkspaceController;
use App\Http\Controllers\Api\V1\Locality\PostalReferenceLookupController;
use App\Http\Controllers\Api\V1\Platform\Maintenance\MaintenanceController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Tenant\TenantController;
use Illuminate\Support\Facades\Route;

Route::put('/auth/password', SetPasswordController::class);
Route::delete('/auth/password', DestroyPasswordController::class);
Route::delete('/auth/session', DestroyCurrentSessionController::class);
Route::delete('/auth/other-sessions', DestroyOtherSessionsController::class);
Route::get('/auth/session', CurrentSessionController::class);
Route::post('/localities/postal-references/lookup', [PostalReferenceLookupController::class, 'lookup']);
Route::get('/localities/postal-references/lookup-status', [PostalReferenceLookupController::class, 'status']);

Route::post('/authorization/resource-checks', [ResourceAuthorizationController::class, 'checkBatch']);
Route::get('/authorization/personal-workspace/folders', [ResourceAuthorizationController::class, 'personalWorkspaceFolders']);
Route::prefix('/drive/personal')->group(static function (): void {
    Route::get('/tree', [DriveWorkspaceController::class, 'personalTree']);
    Route::get('/locations/root', [DriveWorkspaceController::class, 'personalRoot']);
    Route::get('/folders/{folderId}', [DriveWorkspaceController::class, 'personalFolder'])->whereNumber('folderId');
    Route::get('/trash', [DriveWorkspaceController::class, 'personalTrash']);
    Route::get('/items/{itemType}/{itemId}/details', [DriveWorkspaceController::class, 'personalDetails'])->where(['itemType' => 'folder|file', 'itemId' => '[0-9]+']);
    Route::post('/folders', [DriveWorkspaceController::class, 'personalStoreFolder']);
    Route::post('/uploads', [DriveWorkspaceController::class, 'personalUpload']);
    Route::get('/files/{possessionId}/download', [DriveWorkspaceController::class, 'personalDownload'])->whereNumber('possessionId');
    Route::patch('/folders/{folderId}', [DriveWorkspaceController::class, 'personalUpdateFolder'])->whereNumber('folderId');
    Route::post('/folders/{folderId}/move', [DriveWorkspaceController::class, 'personalMoveFolder'])->whereNumber('folderId');
    Route::post('/files/{possessionId}/move', [DriveWorkspaceController::class, 'personalMoveFile'])->whereNumber('possessionId');
    Route::post('/items/trash', [DriveWorkspaceController::class, 'personalTrashItems']);
    Route::post('/items/restore', [DriveWorkspaceController::class, 'personalRestore']);
    Route::post('/items/release', [DriveWorkspaceController::class, 'personalRelease']);
});
Route::prefix('/tenants/{tenantId}/drive')->whereNumber('tenantId')->group(static function (): void {
    Route::get('/tree', [DriveWorkspaceController::class, 'workTree']);
    Route::get('/locations/root', [DriveWorkspaceController::class, 'workRoot']);
    Route::get('/folders/{folderId}', [DriveWorkspaceController::class, 'workFolder'])->whereNumber('folderId');
    Route::get('/trash', [DriveWorkspaceController::class, 'workTrash']);
    Route::get('/items/{itemType}/{itemId}/details', [DriveWorkspaceController::class, 'workDetails'])->where(['itemType' => 'folder|file', 'itemId' => '[0-9]+']);
    Route::post('/folders', [DriveWorkspaceController::class, 'workStoreFolder']);
    Route::post('/uploads', [DriveWorkspaceController::class, 'workUpload']);
    Route::get('/files/{possessionId}/download', [DriveWorkspaceController::class, 'workDownload'])->whereNumber('possessionId');
    Route::patch('/folders/{folderId}', [DriveWorkspaceController::class, 'workUpdateFolder'])->whereNumber('folderId');
    Route::post('/folders/{folderId}/move', [DriveWorkspaceController::class, 'workMoveFolder'])->whereNumber('folderId');
    Route::post('/files/{possessionId}/move', [DriveWorkspaceController::class, 'workMoveFile'])->whereNumber('possessionId');
    Route::post('/items/trash', [DriveWorkspaceController::class, 'workTrashItems']);
    Route::post('/items/restore', [DriveWorkspaceController::class, 'workRestore']);
    Route::post('/items/release', [DriveWorkspaceController::class, 'workRelease']);
});
Route::prefix('/tenants/{tenantId}/authorization')->whereNumber('tenantId')->group(static function (): void {
    Route::post('/advanced/policies', [AdvancedAuthorizationAdministrationController::class, 'publishPolicy']);
    Route::post('/advanced/policies/{policyId}/bindings', [AdvancedAuthorizationAdministrationController::class, 'bindPolicy'])->whereNumber('policyId');
    Route::post('/advanced/separation-rules', [AdvancedAuthorizationAdministrationController::class, 'createSeparationRule']);
    Route::post('/advanced/delegations', [AdvancedAuthorizationAdministrationController::class, 'createDelegation']);
    Route::post('/advanced/delegations/{delegationId}/revocation', [AdvancedAuthorizationAdministrationController::class, 'revokeDelegation'])->whereNumber('delegationId');
    Route::get('/advanced/access-requests', [AdvancedAuthorizationAdministrationController::class, 'accessRequests']);
    Route::post('/advanced/access-requests', [AdvancedAuthorizationAdministrationController::class, 'requestAccess']);
    Route::post('/advanced/access-requests/{requestId}/approval', [AdvancedAuthorizationAdministrationController::class, 'approveAccess'])->whereNumber('requestId');
    Route::post('/advanced/access-requests/{requestId}/revocation', [AdvancedAuthorizationAdministrationController::class, 'revokeAccess'])->whereNumber('requestId');
    Route::post('/advanced/service-identities', [AdvancedAuthorizationAdministrationController::class, 'createServiceIdentity']);
    Route::post('/advanced/service-identities/{identityId}/permissions', [AdvancedAuthorizationAdministrationController::class, 'grantServiceIdentityPermission'])->whereNumber('identityId');
    Route::post('/advanced/service-identities/{identityId}/credentials', [AdvancedAuthorizationAdministrationController::class, 'issueCredential'])->whereNumber('identityId');
    Route::post('/advanced/service-credentials/{credentialId}/revocation', [AdvancedAuthorizationAdministrationController::class, 'revokeCredential'])->whereNumber('credentialId');
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
