<?php

namespace App\Services\Authorization;

use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationRolePermissionService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit) {}

    public function grant(AuthorizationRole $role, AuthorizationPermission $permission): void
    {
        if (! $role->active || ! $permission->active) {
            throw new LogicException('Inactive authorization entities cannot grant permissions.');
        }

        if ($role->scope !== $permission->scope) {
            throw new LogicException('Authorization role and permission scopes must match.');
        }

        DB::transaction(function () use ($role, $permission): void {
            $created = DB::table('auth_role_permission')->insertOrIgnore([
                'idRole' => $role->id,
                'idPermission' => $permission->id,
            ]);

            if ($created === 1) {
                $this->audit->record(
                    'authorization.role_permission.granted',
                    'authorization.role_permission',
                    $permission->id,
                    after: ['idRole' => $role->id, 'idPermission' => $permission->id],
                );
            }
        });
    }
}
