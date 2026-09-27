<?php

namespace App\Services\Authorization;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationGroup;
use App\Services\Authorization\Performance\PolicyVersionService;
use Illuminate\Support\Facades\DB;
use LogicException;

class AuthorizationGroupHierarchyService
{
    public function __construct(private readonly AuthorizationAuditLogger $audit, private readonly PolicyVersionService $policyVersions) {}

    public function addChildGroup(AuthorizationGroup $parent, AuthorizationGroup $child, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($parent, $child, $actorUserId, $correlationId): void {
            [$parent, $child] = $this->lockGroups($parent->id, $child->id);
            $this->validateCompatibility($parent, $child);

            if ($this->wouldCreateCycle($parent->id, $child->id)) {
                throw new LogicException('An authorization group hierarchy cannot contain a cycle.');
            }

            $created = DB::table('auth_group_group')->insertOrIgnore([
                'idParentGroup' => $parent->id,
                'idChildGroup' => $child->id,
            ]);
            if ($created === 1) {
                $this->audit->record(
                    'authorization.group_child.added',
                    'authorization.group',
                    $parent->id,
                    actorUserId: $actorUserId,
                    tenantId: $parent->idTenant,
                    after: ['idChildGroup' => $child->id],
                    correlationId: $correlationId,
                );
                $this->policyVersions->invalidate(AuthorizationScope::from($parent->scope), $parent->idTenant);
            }
        });
    }

    public function removeChildGroup(AuthorizationGroup $parent, AuthorizationGroup $child, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        DB::transaction(function () use ($parent, $child, $actorUserId, $correlationId): void {
            [$parent, $child] = $this->lockGroups($parent->id, $child->id);
            $deleted = DB::table('auth_group_group')
                ->where('idParentGroup', $parent->id)
                ->where('idChildGroup', $child->id)
                ->delete();
            if ($deleted === 1) {
                $this->audit->record(
                    'authorization.group_child.removed',
                    'authorization.group',
                    $parent->id,
                    actorUserId: $actorUserId,
                    tenantId: $parent->idTenant,
                    before: ['idChildGroup' => $child->id],
                    correlationId: $correlationId,
                );
                $this->policyVersions->invalidate(AuthorizationScope::from($parent->scope), $parent->idTenant);
            }
        });
    }

    /** @return array{AuthorizationGroup, AuthorizationGroup} */
    private function lockGroups(int $parentId, int $childId): array
    {
        $groups = AuthorizationGroup::query()->whereIn('id', [$parentId, $childId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $parent = $groups->get($parentId);
        $child = $groups->get($childId);

        if ($parent === null || $child === null) {
            throw new LogicException('Both authorization groups must exist.');
        }

        return [$parent, $child];
    }

    private function validateCompatibility(AuthorizationGroup $parent, AuthorizationGroup $child): void
    {
        if ($parent->id === $child->id) {
            throw new LogicException('An authorization group cannot be its own child.');
        }

        if (! $parent->active || ! $child->active) {
            throw new LogicException('Inactive authorization groups cannot be linked.');
        }

        if ($parent->scope !== $child->scope || $parent->idTenant !== $child->idTenant) {
            throw new LogicException('Authorization group hierarchy requires the same scope and tenant context.');
        }
    }

    private function wouldCreateCycle(int $parentId, int $childId): bool
    {
        $result = DB::selectOne(
            'WITH RECURSIVE ancestors(id) AS (
                SELECT idParentGroup FROM auth_group_group WHERE idChildGroup = ?
                UNION
                SELECT relation.idParentGroup FROM auth_group_group relation
                INNER JOIN ancestors ON ancestors.id = relation.idChildGroup
            )
            SELECT EXISTS(SELECT 1 FROM ancestors WHERE id = ?) AS cycle',
            [$parentId, $childId],
        );

        return (bool) $result->cycle;
    }
}
