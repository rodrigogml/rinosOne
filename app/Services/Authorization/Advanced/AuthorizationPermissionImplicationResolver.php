<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use Illuminate\Support\Facades\DB;

/** Resolves the permissions that can satisfy a requested permission through explicit implications. */
class AuthorizationPermissionImplicationResolver
{
    /** @return list<int> */
    public function grantPermissionIdsFor(string $permissionKey, AuthorizationScope $scope): array
    {
        $permissions = DB::select(
            'WITH RECURSIVE grant_permission(id) AS (
                SELECT id
                FROM auth_permission
                WHERE `key` = ? AND scope = ? AND active = 1
                UNION
                SELECT implication.idPermission
                FROM auth_permission_implication implication
                INNER JOIN grant_permission implied ON implied.id = implication.idImpliedPermission
                INNER JOIN auth_permission permission ON permission.id = implication.idPermission
                WHERE permission.scope = ? AND permission.active = 1
            )
            SELECT id FROM grant_permission',
            [$permissionKey, $scope->value, $scope->value],
        );

        return array_map(static fn (object $permission): int => (int) $permission->id, $permissions);
    }
}
