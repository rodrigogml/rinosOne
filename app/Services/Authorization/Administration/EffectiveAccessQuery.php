<?php

namespace App\Services\Authorization\Administration;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Projects the authorization factors currently applicable to one tenant member.
 *
 * The projection deliberately contains identifiers and authorization metadata only;
 * profile data belonging to other people is never loaded into an administrative view.
 */
class EffectiveAccessQuery
{
    /**
     * @return array{membership: array{id: int, state: string}|null, directRoles: list<array{id: int, key: string, permissions: list<string>}>, groups: list<array{id: int, displayName: string}>, groupRoles: list<array{id: int, key: string, groupId: int, permissions: list<string>}>, restrictions: list<array{id: int, permissionKey: string, subjectType: string, subjectId: int, startsAt: ?string, endsAt: ?string}>, resourceRelations: list<array{id: int, resourceType: string, resourceId: int, relationKey: string, subjectType: string, subjectId: int}>}
     */
    public function forTenantMember(User $subject, int $tenantId): array
    {
        $membership = DB::table('tenantMembership')->where('idTenant', $tenantId)->where('idUser', $subject->id)->first(['id', 'state']);
        $groups = $this->eligibleGroups($subject->id, $tenantId);
        $groupIds = array_map(static fn (object $group): int => (int) $group->id, $groups);

        return [
            'membership' => $membership === null ? null : ['id' => (int) $membership->id, 'state' => $membership->state],
            'directRoles' => $this->rolesForAssignments(DB::table('auth_role_assignment')->where('idUser', $subject->id)->where('idTenant', $tenantId)->where('state', 'ACTIVE')->get(['idRole'])->pluck('idRole')->all()),
            'groups' => array_map(static fn (object $group): array => ['id' => (int) $group->id, 'displayName' => $group->displayName], $groups),
            'groupRoles' => $this->groupRoles($groupIds, $tenantId),
            'restrictions' => $this->restrictions($subject->id, $groupIds, $tenantId),
            'resourceRelations' => $this->resourceRelations($subject->id, $groupIds, $tenantId),
        ];
    }

    /** @return list<object> */
    private function eligibleGroups(int $userId, int $tenantId): array
    {
        return DB::select(
            "WITH RECURSIVE eligible_group(id, displayName) AS (
                SELECT direct_group.id, direct_group.displayName
                FROM auth_group_user
                INNER JOIN auth_group direct_group ON direct_group.id = auth_group_user.idGroup
                WHERE auth_group_user.idUser = ? AND direct_group.active = 1 AND direct_group.scope = 'TENANT' AND direct_group.idTenant = ?
                UNION
                SELECT parent_group.id, parent_group.displayName
                FROM auth_group_group relation
                INNER JOIN eligible_group ON eligible_group.id = relation.idChildGroup
                INNER JOIN auth_group parent_group ON parent_group.id = relation.idParentGroup
                WHERE parent_group.active = 1 AND parent_group.scope = 'TENANT' AND parent_group.idTenant = ?
            ) SELECT id, displayName FROM eligible_group ORDER BY id",
            [$userId, $tenantId, $tenantId],
        );
    }

    /** @param list<int> $roleIds @return list<array{id: int, key: string, permissions: list<string>}> */
    private function rolesForAssignments(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        return DB::table('auth_role')
            ->leftJoin('auth_role_permission', 'auth_role_permission.idRole', '=', 'auth_role.id')
            ->leftJoin('auth_permission', 'auth_permission.id', '=', 'auth_role_permission.idPermission')
            ->whereIn('auth_role.id', $roleIds)->where('auth_role.active', true)
            ->orderBy('auth_role.id')->get(['auth_role.id', 'auth_role.key', 'auth_permission.key as permissionKey'])
            ->groupBy('id')->map(static fn ($rows): array => ['id' => (int) $rows->first()->id, 'key' => $rows->first()->key, 'permissions' => $rows->pluck('permissionKey')->filter()->values()->all()])->values()->all();
    }

    /** @param list<int> $groupIds @return list<array{id: int, key: string, groupId: int, permissions: list<string>}> */
    private function groupRoles(array $groupIds, int $tenantId): array
    {
        if ($groupIds === []) {
            return [];
        }

        return DB::table('auth_group_role_assignment')->join('auth_role', 'auth_role.id', '=', 'auth_group_role_assignment.idRole')
            ->leftJoin('auth_role_permission', 'auth_role_permission.idRole', '=', 'auth_role.id')->leftJoin('auth_permission', 'auth_permission.id', '=', 'auth_role_permission.idPermission')
            ->whereIn('auth_group_role_assignment.idGroup', $groupIds)->where('auth_group_role_assignment.idTenant', $tenantId)->where('auth_group_role_assignment.state', 'ACTIVE')->where('auth_role.active', true)
            ->orderBy('auth_role.id')->get(['auth_role.id', 'auth_role.key', 'auth_group_role_assignment.idGroup as groupId', 'auth_permission.key as permissionKey'])
            ->groupBy(fn (object $row): string => $row->id.'-'.$row->groupId)->map(static fn ($rows): array => ['id' => (int) $rows->first()->id, 'key' => $rows->first()->key, 'groupId' => (int) $rows->first()->groupId, 'permissions' => $rows->pluck('permissionKey')->filter()->values()->all()])->values()->all();
    }

    /** @param list<int> $groupIds @return list<array{id: int, permissionKey: string, subjectType: string, subjectId: int, startsAt: ?string, endsAt: ?string}> */
    private function restrictions(int $userId, array $groupIds, int $tenantId): array
    {
        return DB::table('auth_restriction')->join('auth_permission', 'auth_permission.id', '=', 'auth_restriction.idPermission')
            ->where('auth_restriction.idTenant', $tenantId)->where('auth_restriction.scope', 'TENANT')->where('auth_restriction.active', true)
            ->where(fn ($query) => $query->where('auth_restriction.idUser', $userId)->orWhereIn('auth_restriction.idGroup', $groupIds))
            ->orderBy('auth_restriction.id')->get(['auth_restriction.id', 'auth_permission.key as permissionKey', 'auth_restriction.idUser', 'auth_restriction.idGroup', 'auth_restriction.startsAt', 'auth_restriction.endsAt'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'permissionKey' => $row->permissionKey, 'subjectType' => $row->idUser === null ? 'GROUP' : 'USER', 'subjectId' => (int) ($row->idUser ?? $row->idGroup), 'startsAt' => $row->startsAt, 'endsAt' => $row->endsAt])->all();
    }

    /** @param list<int> $groupIds @return list<array{id: int, resourceType: string, resourceId: int, relationKey: string, subjectType: string, subjectId: int}> */
    private function resourceRelations(int $userId, array $groupIds, int $tenantId): array
    {
        return DB::table('auth_resource_relation')->join('auth_resource_type', 'auth_resource_type.id', '=', 'auth_resource_relation.idResourceType')
            ->where('auth_resource_relation.idTenant', $tenantId)->where('auth_resource_relation.scope', 'TENANT')->where('auth_resource_relation.active', true)
            ->where(fn ($query) => $query->where('auth_resource_relation.idUser', $userId)->orWhereIn('auth_resource_relation.idGroup', $groupIds))
            ->orderBy('auth_resource_relation.id')->get(['auth_resource_relation.id', 'auth_resource_type.key as resourceType', 'auth_resource_relation.resourceId', 'auth_resource_relation.relationKey', 'auth_resource_relation.idUser', 'auth_resource_relation.idGroup'])
            ->map(static fn (object $row): array => ['id' => (int) $row->id, 'resourceType' => $row->resourceType, 'resourceId' => (int) $row->resourceId, 'relationKey' => $row->relationKey, 'subjectType' => $row->idUser === null ? 'GROUP' : 'USER', 'subjectId' => (int) ($row->idUser ?? $row->idGroup)])->all();
    }
}
