<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\TenantAdministratorInvariant;
use Illuminate\Support\Facades\DB;

/**
 * Projects the lazy root catalog visible to an authenticated Drive principal.
 * A Work root requires either administrator access or an authorized navigable folder.
 */
class DriveCatalogService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly TenantAdministratorInvariant $tenantAdministrators,
    ) {}

    /** @return array{drives: list<array<string, mixed>>, sharedWithMe: array{kind: string, displayName: string}} */
    public function catalog(User $principal): array
    {
        $drives = [[
            'target' => ['kind' => 'personal', 'tenantId' => null],
            'displayName' => 'Meu Drive',
            'category' => 'PERSONAL',
            'usage' => $this->usage(AuthorizationScope::Personal, $principal->id),
        ]];

        foreach ($this->candidateTenants($principal) as $tenant) {
            if (! $this->canExposeWorkRoot($principal, $tenant)) {
                continue;
            }

            $drives[] = [
                'target' => ['kind' => 'tenant', 'tenantId' => $tenant->id],
                'displayName' => $tenant->displayName,
                'category' => 'TENANT',
                'usage' => $this->usage(AuthorizationScope::Tenant, $tenant->id),
            ];
        }

        return [
            'drives' => $drives,
            'sharedWithMe' => ['kind' => 'shared-with-me', 'displayName' => 'Compartilhados comigo'],
        ];
    }

    /**
     * Confirms that the principal may address a Work target without converting a valid
     * authorization denial into a target-not-found response. Visibility is stricter.
     */
    public function canResolveWork(User $principal, int $tenantId): bool
    {
        foreach ($this->candidateTenants($principal) as $tenant) {
            if ($tenant->id === $tenantId) {
                return true;
            }
        }

        return false;
    }

    /** @return iterable<Tenant> */
    private function candidateTenants(User $principal): iterable
    {
        $groupIds = $this->eligibleTenantGroupIds($principal);

        return Tenant::query()
            ->where('state', TenantState::Active)
            ->whereExists(function ($query) use ($principal): void {
                $query->selectRaw('1')
                    ->from('tenantMembership')
                    ->whereColumn('tenantMembership.idTenant', 'tenant.id')
                    ->where('tenantMembership.idUser', $principal->id)
                    ->where('tenantMembership.state', TenantMembershipState::Active->value);
            })
            ->where(function ($query) use ($principal, $groupIds): void {
                $query->whereExists(function ($administrator) use ($principal): void {
                    $administrator->selectRaw('1')
                        ->from('auth_role_assignment')
                        ->join('auth_role', 'auth_role.id', '=', 'auth_role_assignment.idRole')
                        ->whereColumn('auth_role_assignment.idTenant', 'tenant.id')
                        ->where('auth_role_assignment.idUser', $principal->id)
                        ->where('auth_role_assignment.state', 'ACTIVE')
                        ->where('auth_role.key', 'tenant.administrator');
                })->orWhereExists(function ($relation) use ($principal, $groupIds): void {
                    $relation->selectRaw('1')
                        ->from('auth_resource_relation')
                        ->join('auth_resource_type', 'auth_resource_type.id', '=', 'auth_resource_relation.idResourceType')
                        ->join('file_workspaceFolder', 'file_workspaceFolder.id', '=', 'auth_resource_relation.resourceId')
                        ->whereColumn('auth_resource_relation.idTenant', 'tenant.id')
                        ->whereColumn('file_workspaceFolder.idTenant', 'tenant.id')
                        ->where('auth_resource_type.key', 'tenant.folder')
                        ->where('auth_resource_type.active', true)
                        ->where('auth_resource_relation.scope', AuthorizationScope::Tenant->value)
                        ->whereIn('auth_resource_relation.relationKey', ['READ', 'EDIT'])
                        ->where('auth_resource_relation.active', true)
                        ->where('file_workspaceFolder.state', 'ACTIVE')
                        ->where(function ($subject) use ($principal, $groupIds): void {
                            $subject->where('auth_resource_relation.idUser', $principal->id);
                            if ($groupIds !== []) {
                                $subject->orWhereIn('auth_resource_relation.idGroup', $groupIds);
                            }
                        });
                });
            })
            ->orderBy('displayName')
            ->get();
    }

    private function canExposeWorkRoot(User $principal, Tenant $tenant): bool
    {
        $rootDecision = $this->authorization->check($principal, 'tenant.folder.read', AuthorizationScope::Tenant, $tenant->id);
        if ($rootDecision->reasonCode === 'RESTRICTION_APPLIES') {
            return false;
        }

        if ($this->tenantAdministrators->isActiveDirectAdministrator($principal, $tenant->id)) {
            return true;
        }

        foreach ($this->candidateFolderIds($principal, $tenant->id) as $folderId) {
            $resource = new ResourceReference('tenant.folder', $folderId, AuthorizationScope::Tenant, $tenant->id);
            $read = $this->authorization->check($principal, 'tenant.folder.read', AuthorizationScope::Tenant, $tenant->id, $resource)->allowed;
            $edit = $this->authorization->check($principal, 'tenant.folder.edit', AuthorizationScope::Tenant, $tenant->id, $resource)->allowed;
            if ($read || $edit) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> */
    private function candidateFolderIds(User $principal, int $tenantId): array
    {
        $groupIds = $this->eligibleTenantGroupIds($principal);

        return DB::table('auth_resource_relation')
            ->join('auth_resource_type', 'auth_resource_type.id', '=', 'auth_resource_relation.idResourceType')
            ->join('file_workspaceFolder', 'file_workspaceFolder.id', '=', 'auth_resource_relation.resourceId')
            ->where('auth_resource_type.key', 'tenant.folder')
            ->where('auth_resource_type.active', true)
            ->where('auth_resource_relation.idTenant', $tenantId)
            ->where('auth_resource_relation.scope', AuthorizationScope::Tenant->value)
            ->whereIn('auth_resource_relation.relationKey', ['READ', 'EDIT'])
            ->where('auth_resource_relation.active', true)
            ->where('file_workspaceFolder.idTenant', $tenantId)
            ->where('file_workspaceFolder.state', 'ACTIVE')
            ->where(function ($subject) use ($principal, $groupIds): void {
                $subject->where('auth_resource_relation.idUser', $principal->id);
                if ($groupIds !== []) {
                    $subject->orWhereIn('auth_resource_relation.idGroup', $groupIds);
                }
            })
            ->distinct()
            ->pluck('auth_resource_relation.resourceId')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function eligibleTenantGroupIds(User $principal): array
    {
        return collect(DB::select(
            <<<'SQL'
                WITH RECURSIVE eligible_group(id) AS (
                    SELECT direct_group.id
                    FROM auth_group_user
                    INNER JOIN auth_group direct_group ON direct_group.id = auth_group_user.idGroup
                    WHERE auth_group_user.idUser = ?
                        AND direct_group.active = 1
                        AND direct_group.scope = 'TENANT'
                    UNION
                    SELECT relation.idParentGroup
                    FROM auth_group_group relation
                    INNER JOIN eligible_group ON eligible_group.id = relation.idChildGroup
                    INNER JOIN auth_group parent_group ON parent_group.id = relation.idParentGroup
                    WHERE parent_group.active = 1
                        AND parent_group.scope = 'TENANT'
                )
                SELECT id FROM eligible_group
                SQL,
            [$principal->id],
        ))->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /** @return array{workspaceBytes: int, systemManagedBytes: int, trashBytes: int, totalBytes: int} */
    private function usage(AuthorizationScope $scope, int $ownerId): array
    {
        $usage = StoredFileOwnerUsage::query()
            ->where($scope === AuthorizationScope::Personal ? 'idUser' : 'idTenant', $ownerId)
            ->first();

        return [
            'workspaceBytes' => (int) ($usage?->workspaceBytes ?? 0),
            'systemManagedBytes' => (int) ($usage?->systemManagedBytes ?? 0),
            'trashBytes' => (int) ($usage?->trashBytes ?? 0),
            'totalBytes' => (int) ($usage?->totalBytes ?? 0),
        ];
    }
}
