<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationSeparationRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationSeparationEvaluator
{
    /** @param callable(string): bool $hasEffectivePermission */
    public function violates(string $permissionKey, AuthorizationScope $scope, ?int $tenantId, callable $hasEffectivePermission): bool
    {
        $permission = AuthorizationPermission::query()->where('key', $permissionKey)->where('scope', $scope->value)->first();
        if ($permission === null) {
            return false;
        }
        $fingerprint = $scope === AuthorizationScope::Tenant ? "tenant:{$tenantId}" : strtolower($scope->value);
        $otherPermissionIds = AuthorizationSeparationRule::query()
            ->where('scope', $scope->value)->where('contextFingerprint', $fingerprint)->where('active', true)
            ->where(fn ($query) => $query->where('idPermission', $permission->id)->orWhere('idIncompatiblePermission', $permission->id))
            ->get()->map(fn (AuthorizationSeparationRule $rule): int => $rule->idPermission === $permission->id ? $rule->idIncompatiblePermission : $rule->idPermission);
        $otherKeys = AuthorizationPermission::query()->whereIn('id', $otherPermissionIds)->where('active', true)->pluck('key');

        foreach ($otherKeys as $otherKey) {
            if ($hasEffectivePermission($otherKey)) {
                return true;
            }
        }

        return false;
    }

    public function assertDirectRoleAssignmentAllowed(AuthorizationRole $role, User $user, int $tenantId): void
    {
        $candidatePermissionIds = DB::table('auth_role_permission')->where('idRole', $role->id)->pluck('idPermission');
        $existingPermissionIds = DB::table('auth_role_assignment')
            ->join('auth_role_permission', 'auth_role_permission.idRole', '=', 'auth_role_assignment.idRole')
            ->where('auth_role_assignment.idUser', $user->id)->where('auth_role_assignment.idTenant', $tenantId)->where('auth_role_assignment.state', 'ACTIVE')
            ->lockForUpdate()->pluck('auth_role_permission.idPermission');
        if ($candidatePermissionIds->isEmpty() || $existingPermissionIds->isEmpty()) {
            return;
        }
        $conflict = AuthorizationSeparationRule::query()->where('scope', AuthorizationScope::Tenant->value)->where('contextFingerprint', "tenant:{$tenantId}")->where('active', true)
            ->where(function ($query) use ($candidatePermissionIds, $existingPermissionIds): void {
                $query->where(fn ($pair) => $pair->whereIn('idPermission', $candidatePermissionIds)->whereIn('idIncompatiblePermission', $existingPermissionIds))
                    ->orWhere(fn ($pair) => $pair->whereIn('idPermission', $existingPermissionIds)->whereIn('idIncompatiblePermission', $candidatePermissionIds));
            })->exists();
        if ($conflict) {
            throw new LogicException('The role assignment violates a separation of duties rule.');
        }
    }
}
