<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationRolePermissionService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit, private readonly PolicyVersionService $policyVersions) {}

    public function grant(AuthorizationRole $role, AuthorizationPermission $permission, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        if (! $role->active || ! $permission->active) {
            throw new LogicException('Inactive authorization entities cannot grant permissions.');
        }

        if ($role->scope !== $permission->scope) {
            throw new LogicException('Authorization role and permission scopes must match.');
        }

        DB::transaction(function () use ($role, $permission, $actorUserId, $correlationId): void {
            $created = DB::table('auth_role_permission')->insertOrIgnore([
                'idRole' => $role->id,
                'idPermission' => $permission->id,
            ]);

            if ($created === 1) {
                $this->audit->record(
                    'authorization.role_permission.granted',
                    'authorization.role_permission',
                    $permission->id,
                    actorUserId: $actorUserId,
                    after: ['idRole' => $role->id, 'idPermission' => $permission->id],
                    correlationId: $correlationId,
                );
                $this->policyVersions->invalidate(AuthorizationScope::from($role->scope), $role->idTenant);
            }
        });
    }
}
