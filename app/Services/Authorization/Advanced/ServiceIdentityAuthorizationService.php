<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationServiceCredential;
use App\Models\AuthorizationServiceIdentity;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Support\Facades\DB;
use LogicException;

class ServiceIdentityAuthorizationService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit, private readonly PolicyVersionService $policyVersions) {}

    public function grant(AuthorizationServiceIdentity $identity, AuthorizationPermission $permission): void
    {
        if ($identity->state !== 'ACTIVE' || $permission->scope !== $identity->scope) {
            throw new LogicException('The service identity permission is invalid.');
        }
        if (DB::table('auth_service_identity_permission')->insertOrIgnore(['idServiceIdentity' => $identity->id, 'idPermission' => $permission->id]) === 1) {
            $this->audit->record('authorization.service_identity.permission_granted', 'authorization.service_identity', $identity->id, actorUserId: $identity->idOwnerUser, tenantId: $identity->idTenant, after: ['idPermission' => $permission->id]);
            $this->policyVersions->invalidate(AuthorizationScope::from($identity->scope), $identity->idTenant);
        }
    }

    public function allows(AuthorizationServiceCredential $credential, string $permissionKey): bool
    {
        if ($credential->state !== 'ACTIVE') {
            return false;
        }
        $caps = $credential->permissionKeys;
        if ($caps !== null && ! in_array($permissionKey, $caps, true)) {
            return false;
        }

        return DB::table('auth_service_identity_permission')->join('auth_permission', 'auth_permission.id', '=', 'auth_service_identity_permission.idPermission')->where('auth_service_identity_permission.idServiceIdentity', $credential->idServiceIdentity)->where('auth_permission.key', $permissionKey)->where('auth_permission.active', true)->exists();
    }
}
