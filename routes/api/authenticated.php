<?php

use App\Http\Controllers\Api\V1\Access\CurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyCurrentSessionController;
use App\Http\Controllers\Api\V1\Access\DestroyOtherSessionsController;
use App\Http\Controllers\Api\V1\Access\DestroyPasswordController;
use App\Http\Controllers\Api\V1\Access\SetPasswordController;
use App\Http\Controllers\Api\V1\Authorization\AdvancedAuthorizationAdministrationController;
use App\Http\Controllers\Api\V1\Authorization\AuthorizationAdministrationController;
use App\Http\Controllers\Api\V1\Authorization\ContextualAuthorizationAdministrationController;
use App\Http\Controllers\Api\V1\Authorization\ContextualAuthorizationResourceShareController;
use App\Http\Controllers\Api\V1\Authorization\ResourceAuthorizationController;
use App\Http\Controllers\Api\V1\Drive\DriveWorkspaceController;
use App\Http\Controllers\Api\V1\Locality\PostalReferenceLookupController;
use App\Http\Controllers\Api\V1\Person\PersonController;
use App\Http\Controllers\Api\V1\Person\PersonReferenceCatalogController;
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

Route::prefix('/tenants/{tenantId}/people')->middleware('person.metrics')->whereNumber('tenantId')->group(static function (): void {
    Route::middleware(['api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->get('/references/countries', [PersonReferenceCatalogController::class, 'countries'])->name('people.references.countries');
    Route::middleware(['api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->get('/references/countries/{countryId}/brazil-states', [PersonReferenceCatalogController::class, 'states'])->whereNumber('countryId')->name('people.references.brazil-states');
    Route::middleware(['api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->get('/references/brazil-states/{stateId}/municipalities', [PersonReferenceCatalogController::class, 'municipalities'])->whereNumber('stateId')->name('people.references.brazil-municipalities');
    Route::middleware(['api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->get('/references/financial-institutions', [PersonReferenceCatalogController::class, 'financialInstitutions'])->name('people.references.financial-institutions');
    Route::middleware(['api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->get('/filter-schema', [PersonController::class, 'filterSchema'])->name('people.filter-schema');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->get('/', [PersonController::class, 'index'])->name('people.index');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->post('/query', [PersonController::class, 'query'])->name('people.query');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->post('/selection-ids', [PersonController::class, 'selectionIds'])->name('people.selection-ids');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.create', 'api.idempotency'])
        ->post('/', [PersonController::class, 'store'])->name('people.store');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.read'])
        ->get('/{personId}', [PersonController::class, 'show'])->whereNumber('personId')->name('people.show');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.update', 'api.idempotency'])
        ->put('/{personId}', [PersonController::class, 'update'])->whereNumber('personId')->name('people.update');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.duplicate', 'api.idempotency'])
        ->post('/{personId}/duplicate', [PersonController::class, 'duplicate'])->whereNumber('personId')->name('people.duplicate');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.inactivate', 'api.idempotency'])
        ->post('/{personId}/inactivate', [PersonController::class, 'inactivate'])->whereNumber('personId')->name('people.inactivate');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.reactivate', 'api.idempotency'])
        ->post('/{personId}/reactivate', [PersonController::class, 'reactivate'])->whereNumber('personId')->name('people.reactivate');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.delete'])
        ->get('/{personId}/deletion-usage', [PersonController::class, 'deletionUsage'])->whereNumber('personId')->name('people.deletion-usage');
    Route::middleware(['api.json-size', 'api.rate-limit', 'person.tenant-context:tenant.people.delete', 'api.idempotency'])
        ->delete('/{personId}', [PersonController::class, 'destroy'])->whereNumber('personId')->name('people.destroy');
});

Route::post('/authorization/resource-checks', [ResourceAuthorizationController::class, 'checkBatch']);
Route::get('/authorization/personal-workspace/folders', [ResourceAuthorizationController::class, 'personalWorkspaceFolders']);
Route::get('/drive/catalog', [DriveWorkspaceController::class, 'catalog']);
Route::get('/drive/shared-with-me', [DriveWorkspaceController::class, 'sharedWithMe']);
Route::get('/drive/shared-with-me/files/{possessionId}/details', [DriveWorkspaceController::class, 'sharedFileDetails'])->whereNumber('possessionId');
Route::get('/drive/shared-with-me/files/{possessionId}/download', [DriveWorkspaceController::class, 'sharedDownload'])->whereNumber('possessionId');
Route::post('/drive/shared-with-me/exports', [DriveWorkspaceController::class, 'sharedRequestExport']);
Route::get('/drive/shared-with-me/exports/{exportId}', [DriveWorkspaceController::class, 'sharedExportStatus'])->whereUlid('exportId');
Route::post('/drive/shared-with-me/exports/{exportId}/cancel', [DriveWorkspaceController::class, 'sharedCancelExport'])->whereUlid('exportId');
Route::get('/drive/shared-with-me/exports/{exportId}/download', [DriveWorkspaceController::class, 'sharedDownloadExport'])->whereUlid('exportId');
Route::middleware(['api.json-size', 'api.rate-limit', 'api.idempotency'])->post('/drive/transfers', [DriveWorkspaceController::class, 'storeTransfer'])->name('drive.transfers.store');
Route::middleware(['api.rate-limit'])->get('/drive/transfers/{transferId}', [DriveWorkspaceController::class, 'transferStatus'])->whereUlid('transferId')->name('drive.transfers.show');
Route::middleware(['api.json-size', 'api.rate-limit'])->post('/drive/transfers/{transferId}/cancel', [DriveWorkspaceController::class, 'cancelTransfer'])->whereUlid('transferId')->name('drive.transfers.cancel');
Route::prefix('/drive/personal')->group(static function (): void {
    Route::get('/tree', [DriveWorkspaceController::class, 'personalTree']);
    Route::get('/locations/root', [DriveWorkspaceController::class, 'personalRoot']);
    Route::get('/folders/{folderId}', [DriveWorkspaceController::class, 'personalFolder'])->whereNumber('folderId');
    Route::get('/trash', [DriveWorkspaceController::class, 'personalTrash']);
    Route::get('/items/{itemType}/{itemId}/details', [DriveWorkspaceController::class, 'personalDetails'])->where(['itemType' => 'folder|file', 'itemId' => '[0-9]+']);
    Route::post('/folders', [DriveWorkspaceController::class, 'personalStoreFolder']);
    Route::post('/uploads', [DriveWorkspaceController::class, 'personalUpload']);
    Route::post('/exports', [DriveWorkspaceController::class, 'personalRequestExport']);
    Route::get('/exports/{exportId}', [DriveWorkspaceController::class, 'personalExportStatus'])->whereUlid('exportId');
    Route::post('/exports/{exportId}/cancel', [DriveWorkspaceController::class, 'personalCancelExport'])->whereUlid('exportId');
    Route::get('/exports/{exportId}/download', [DriveWorkspaceController::class, 'personalDownloadExport'])->whereUlid('exportId');
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
    Route::post('/exports', [DriveWorkspaceController::class, 'workRequestExport']);
    Route::get('/exports/{exportId}', [DriveWorkspaceController::class, 'workExportStatus'])->whereUlid('exportId');
    Route::post('/exports/{exportId}/cancel', [DriveWorkspaceController::class, 'workCancelExport'])->whereUlid('exportId');
    Route::get('/exports/{exportId}/download', [DriveWorkspaceController::class, 'workDownloadExport'])->whereUlid('exportId');
    Route::get('/files/{possessionId}/download', [DriveWorkspaceController::class, 'workDownload'])->whereNumber('possessionId');
    Route::patch('/folders/{folderId}', [DriveWorkspaceController::class, 'workUpdateFolder'])->whereNumber('folderId');
    Route::post('/folders/{folderId}/move', [DriveWorkspaceController::class, 'workMoveFolder'])->whereNumber('folderId');
    Route::post('/files/{possessionId}/move', [DriveWorkspaceController::class, 'workMoveFile'])->whereNumber('possessionId');
    Route::post('/items/trash', [DriveWorkspaceController::class, 'workTrashItems']);
    Route::post('/items/restore', [DriveWorkspaceController::class, 'workRestore']);
    Route::post('/items/release', [DriveWorkspaceController::class, 'workRelease']);
});
Route::prefix('/tenants/{tenantId}/authorization')->whereNumber('tenantId')->group(static function (): void {
    Route::get('/context', [ContextualAuthorizationAdministrationController::class, 'tenantContext']);
    Route::get('/subjects', [ContextualAuthorizationAdministrationController::class, 'tenantSubjects']);
    Route::get('/roles', [ContextualAuthorizationAdministrationController::class, 'tenantRoles']);
    Route::get('/groups/contextual', [ContextualAuthorizationAdministrationController::class, 'tenantGroups']);
    Route::get('/permissions', [ContextualAuthorizationAdministrationController::class, 'tenantPermissions']);
    Route::get('/subjects/{subjectType}/{subjectId}/effective-access', [ContextualAuthorizationAdministrationController::class, 'tenantEffectiveAccess'])->where(['subjectType' => 'USER|SERVICE_IDENTITY', 'subjectId' => '[0-9]+']);
    Route::post('/subjects/{subjectType}/{subjectId}/explain', [ContextualAuthorizationAdministrationController::class, 'tenantExplain'])->where(['subjectType' => 'USER|SERVICE_IDENTITY', 'subjectId' => '[0-9]+']);
    Route::get('/audit-events/contextual', [ContextualAuthorizationAdministrationController::class, 'tenantAuditEvents']);
    Route::get('/resources/{resourceType}/{resourceId}/shares', [ContextualAuthorizationResourceShareController::class, 'tenantIndex'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+']);
    Route::get('/share-recipients', [ContextualAuthorizationResourceShareController::class, 'tenantRecipients']);
    Route::post('/resources/{resourceType}/{resourceId}/shares', [ContextualAuthorizationResourceShareController::class, 'tenantStore'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+']);
    Route::patch('/resources/{resourceType}/{resourceId}/shares/{shareId}', [ContextualAuthorizationResourceShareController::class, 'tenantUpdate'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+', 'shareId' => '[0-9]+']);
    Route::delete('/resources/{resourceType}/{resourceId}/shares/{shareId}', [ContextualAuthorizationResourceShareController::class, 'tenantDestroy'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+', 'shareId' => '[0-9]+']);
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

Route::prefix('/authorization/personal')->group(static function (): void {
    Route::get('/context', [ContextualAuthorizationAdministrationController::class, 'personalContext']);
    Route::get('/subjects', [ContextualAuthorizationAdministrationController::class, 'personalSubjects']);
    Route::get('/roles', [ContextualAuthorizationAdministrationController::class, 'personalRoles']);
    Route::get('/groups/contextual', [ContextualAuthorizationAdministrationController::class, 'personalGroups']);
    Route::get('/permissions', [ContextualAuthorizationAdministrationController::class, 'personalPermissions']);
    Route::get('/subjects/{subjectType}/{subjectId}/effective-access', [ContextualAuthorizationAdministrationController::class, 'personalEffectiveAccess'])->where(['subjectType' => 'USER|SERVICE_IDENTITY', 'subjectId' => '[0-9]+']);
    Route::post('/subjects/{subjectType}/{subjectId}/explain', [ContextualAuthorizationAdministrationController::class, 'personalExplain'])->where(['subjectType' => 'USER|SERVICE_IDENTITY', 'subjectId' => '[0-9]+']);
    Route::get('/audit-events', [ContextualAuthorizationAdministrationController::class, 'personalAuditEvents']);
    Route::get('/resources/{resourceType}/{resourceId}/shares', [ContextualAuthorizationResourceShareController::class, 'personalIndex'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+']);
    Route::get('/share-recipients', [ContextualAuthorizationResourceShareController::class, 'personalRecipients']);
    Route::post('/resources/{resourceType}/{resourceId}/shares', [ContextualAuthorizationResourceShareController::class, 'personalStore'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+']);
    Route::patch('/resources/{resourceType}/{resourceId}/shares/{shareId}', [ContextualAuthorizationResourceShareController::class, 'personalUpdate'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+', 'shareId' => '[0-9]+']);
    Route::delete('/resources/{resourceType}/{resourceId}/shares/{shareId}', [ContextualAuthorizationResourceShareController::class, 'personalDestroy'])->where(['resourceType' => 'FOLDER', 'resourceId' => '[0-9]+', 'shareId' => '[0-9]+']);
});

Route::prefix('/platform/authorization')->group(static function (): void {
    Route::get('/context', [ContextualAuthorizationAdministrationController::class, 'platformContext']);
    Route::get('/subjects', [ContextualAuthorizationAdministrationController::class, 'platformSubjects']);
    Route::get('/roles', [ContextualAuthorizationAdministrationController::class, 'platformRoles']);
    Route::get('/groups/contextual', [ContextualAuthorizationAdministrationController::class, 'platformGroups']);
    Route::get('/permissions', [ContextualAuthorizationAdministrationController::class, 'platformPermissions']);
    Route::get('/subjects/{subjectType}/{subjectId}/effective-access', [ContextualAuthorizationAdministrationController::class, 'platformEffectiveAccess'])->where(['subjectType' => 'USER|SERVICE_IDENTITY', 'subjectId' => '[0-9]+']);
    Route::post('/subjects/{subjectType}/{subjectId}/explain', [ContextualAuthorizationAdministrationController::class, 'platformExplain'])->where(['subjectType' => 'USER|SERVICE_IDENTITY', 'subjectId' => '[0-9]+']);
    Route::get('/audit-events', [ContextualAuthorizationAdministrationController::class, 'platformAuditEvents']);
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
