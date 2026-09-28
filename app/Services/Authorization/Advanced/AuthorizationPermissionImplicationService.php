<?php

namespace App\Services\Authorization\Advanced;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Services\Authorization\AuthorizationAuditLogger;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Maintains the acyclic, scope-local graph by which one permission satisfies another permission. */
class AuthorizationPermissionImplicationService
{
    public function __construct(
        private readonly AuthorizationAuditLogger $audit,
        private readonly PolicyVersionService $policyVersions,
    ) {}

    public function create(AuthorizationPermission $permission, AuthorizationPermission $impliedPermission, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($permission, $impliedPermission, $actorUserId, $correlationId): void {
            $permissions = AuthorizationPermission::query()
                ->where('scope', $permission->scope)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $permission = $permissions->get($permission->id);
            $impliedPermission = $permissions->get($impliedPermission->id);
            if ($permission === null || $impliedPermission === null || ! $permission->active || ! $impliedPermission->active) {
                throw new LogicException('A permission implication requires active permissions in the same scope.');
            }
            if ($permission->id === $impliedPermission->id) {
                throw new LogicException('A permission cannot imply itself.');
            }
            if ($this->wouldCreateCycle($permission->id, $impliedPermission->id)) {
                throw new LogicException('A permission implication cannot create a cycle.');
            }
            if (DB::table('auth_permission_implication')->insertOrIgnore([
                'idPermission' => $permission->id,
                'idImpliedPermission' => $impliedPermission->id,
            ]) === 0) {
                return;
            }

            $this->audit->record(
                'authorization.permission_implication.created',
                'authorization.permission_implication',
                $permission->id,
                actorUserId: $actorUserId,
                after: ['idPermission' => $permission->id, 'idImpliedPermission' => $impliedPermission->id, 'scope' => $permission->scope],
                correlationId: $correlationId,
            );
            $this->policyVersions->invalidate(AuthorizationScope::from($permission->scope));
        });
    }

    private function wouldCreateCycle(int $permissionId, int $impliedPermissionId): bool
    {
        return DB::selectOne(
            'WITH RECURSIVE reachable_permission(id) AS (
                SELECT idImpliedPermission FROM auth_permission_implication WHERE idPermission = ?
                UNION
                SELECT implication.idImpliedPermission
                FROM auth_permission_implication implication
                INNER JOIN reachable_permission reachable ON reachable.id = implication.idPermission
            )
            SELECT EXISTS(SELECT 1 FROM reachable_permission WHERE id = ?) AS cycleDetected',
            [$impliedPermissionId, $permissionId],
        )->cycleDetected === 1;
    }
}
