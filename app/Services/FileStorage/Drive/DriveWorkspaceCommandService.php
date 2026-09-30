<?php

namespace App\Services\FileStorage\Drive;

use App\Contracts\FileStorage\V1\FilePossessionOperationRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\TenantAdministratorInvariant;
use App\Services\FileStorage\FileStoragePossessionLifecycleService;
use App\Services\FileStorage\WorkspaceFolderService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Executes atomic Drive commands after resolving the caller's effective folder capabilities. */
class DriveWorkspaceCommandService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly TenantAdministratorInvariant $tenantAdministrators,
        private readonly DriveWorkspaceNameResolver $names,
        private readonly FileStoragePossessionLifecycleService $possessions,
        private readonly WorkspaceFolderService $folders,
        private readonly DriveTransferReservationService $transferReservations,
    ) {}

    public function createFolder(User $principal, DriveWorkspaceTarget $target, string $displayName, ?int $parentFolderId): WorkspaceFolder
    {
        return DB::transaction(function () use ($principal, $target, $displayName, $parentFolderId): WorkspaceFolder {
            $ownerTarget = $this->targetForParent($principal, $target, $parentFolderId);
            $this->lockWorkspace($ownerTarget);
            $this->transferReservations->assertMutationAvailable($ownerTarget, $parentFolderId);
            $this->assertEditableLocation($principal, $ownerTarget, $parentFolderId);
            $name = $this->names->resolve($ownerTarget, $parentFolderId, $displayName);

            return WorkspaceFolder::query()->create([
                $ownerTarget->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant' => $ownerTarget->ownerId,
                'idParentFolder' => $parentFolderId,
                'displayName' => $name,
                'state' => 'ACTIVE',
            ]);
        });
    }

    /**
     * Resolves and locks an editable destination while the caller transaction remains open.
     *
     * The returned target is the actual owner of a personal shared folder, not necessarily
     * the workspace initially requested by the principal.
     *
     * @throws DriveWorkspaceCommandException when the destination is unavailable or not editable.
     */
    public function lockEditableDestination(User $principal, DriveWorkspaceTarget $target, ?int $parentFolderId): DriveWorkspaceTarget
    {
        $ownerTarget = $this->targetForParent($principal, $target, $parentFolderId);
        $this->lockWorkspace($ownerTarget);
        $this->transferReservations->assertMutationAvailable($ownerTarget, $parentFolderId);
        $this->assertEditableLocation($principal, $ownerTarget, $parentFolderId);

        return $ownerTarget;
    }

    public function renameFolder(User $principal, DriveWorkspaceTarget $target, int $folderId, string $displayName): WorkspaceFolder
    {
        return DB::transaction(function () use ($principal, $target, $folderId, $displayName): WorkspaceFolder {
            $folder = $this->activeFolder($target, $folderId);
            $ownerTarget = $this->targetForFolder($target, $folder);
            $this->lockWorkspace($ownerTarget);
            $this->transferReservations->assertMutationAvailable($ownerTarget, $folder->id);
            $this->assertEditableFolder($principal, $ownerTarget, $folder);
            $folder->forceFill(['displayName' => $this->names->resolve($ownerTarget, $folder->idParentFolder, $displayName, $folder->id)])->save();

            return $folder;
        });
    }

    public function moveFolder(User $principal, DriveWorkspaceTarget $target, int $folderId, ?int $destinationFolderId): WorkspaceFolder
    {
        return DB::transaction(function () use ($principal, $target, $folderId, $destinationFolderId): WorkspaceFolder {
            $folder = $this->activeFolder($target, $folderId);
            $ownerTarget = $this->targetForFolder($target, $folder);
            $this->lockWorkspace($ownerTarget);
            $this->transferReservations->assertMutationAvailable($ownerTarget, $folder->id);
            $this->transferReservations->assertMutationAvailable($ownerTarget, $destinationFolderId);
            $this->assertEditableFolder($principal, $ownerTarget, $folder);
            $this->assertEditableLocation($principal, $ownerTarget, $destinationFolderId);
            $this->assertDestinationIsNotInFolderTree($folder, $destinationFolderId);
            $folder->forceFill([
                'idParentFolder' => $destinationFolderId,
                'displayName' => $this->names->resolve($ownerTarget, $destinationFolderId, $folder->displayName, $folder->id),
            ])->save();

            return $folder;
        });
    }

    public function moveFile(User $principal, DriveWorkspaceTarget $target, int $possessionId, ?int $destinationFolderId): StoredFilePossession
    {
        return DB::transaction(function () use ($principal, $target, $possessionId, $destinationFolderId): StoredFilePossession {
            $possession = $this->activePossession($target, $possessionId);
            $ownerTarget = $this->targetForPossession($target, $possession);
            $this->lockWorkspace($ownerTarget);
            $this->transferReservations->assertMutationAvailable($ownerTarget, $possession->idWorkspaceFolder);
            $this->transferReservations->assertMutationAvailable($ownerTarget, $destinationFolderId);
            $this->assertEditablePossession($principal, $ownerTarget, $possession);
            $this->assertEditableLocation($principal, $ownerTarget, $destinationFolderId);
            $possession->forceFill([
                'idWorkspaceFolder' => $destinationFolderId,
                'displayName' => $this->names->resolve($ownerTarget, $destinationFolderId, $possession->displayName, null, $possession->id),
            ])->save();

            return $possession;
        });
    }

    /** @param list<array{type: string, id: int}> $items */
    public function trash(User $principal, DriveWorkspaceTarget $target, array $items): void
    {
        $this->performItemOperation($principal, $target, $items, 'trash');
    }

    /** @param list<array{type: string, id: int}> $items */
    public function restore(User $principal, DriveWorkspaceTarget $target, array $items): void
    {
        $this->performItemOperation($principal, $target, $items, 'restore');
    }

    /** @param list<array{type: string, id: int}> $items */
    public function release(User $principal, DriveWorkspaceTarget $target, array $items): void
    {
        $this->performItemOperation($principal, $target, $items, 'release');
    }

    /** @param list<array{type: string, id: int}> $items */
    private function performItemOperation(User $principal, DriveWorkspaceTarget $target, array $items, string $operation): void
    {
        if ($items === [] || count($items) !== count(array_unique(array_map(fn (array $item): string => $item['type'].':'.$item['id'], $items)))) {
            throw new DriveWorkspaceCommandException('DRIVE_INVALID_SELECTION');
        }

        DB::transaction(function () use ($principal, $target, $items, $operation): void {
            foreach ($items as $item) {
                if ($item['type'] === 'folder') {
                    $this->operateFolder($principal, $target, $item['id'], $operation);

                    continue;
                }
                if ($item['type'] === 'file') {
                    $this->operatePossession($principal, $target, $item['id'], $operation);

                    continue;
                }

                throw new DriveWorkspaceCommandException('DRIVE_INVALID_SELECTION');
            }
        });
    }

    private function operateFolder(User $principal, DriveWorkspaceTarget $target, int $folderId, string $operation): void
    {
        $state = $operation === 'restore' || $operation === 'release' ? 'TRASHED' : 'ACTIVE';
        $folder = $this->folder($target, $folderId, $state);
        $ownerTarget = $this->targetForFolder($target, $folder);
        $this->lockWorkspace($ownerTarget);
        $this->transferReservations->assertMutationAvailable($ownerTarget, $folder->id);
        $this->assertEditableFolder($principal, $ownerTarget, $folder);

        if ($operation === 'trash') {
            $this->folders->trash($ownerTarget->ownerType, $ownerTarget->ownerId, $folder->id);

            return;
        }
        if ($operation === 'restore') {
            $this->folders->restore($ownerTarget->ownerType, $ownerTarget->ownerId, $folder->id);

            return;
        }

        $tree = $this->folderTree($ownerTarget, $folder, 'TRASHED');
        $this->releaseFolderTree($ownerTarget, $tree);
    }

    private function operatePossession(User $principal, DriveWorkspaceTarget $target, int $possessionId, string $operation): void
    {
        $state = $operation === 'restore' || $operation === 'release' ? 'TRASHED' : 'ACTIVE';
        $possession = $this->possession($target, $possessionId, $state);
        $ownerTarget = $this->targetForPossession($target, $possession);
        $this->lockWorkspace($ownerTarget);
        $this->transferReservations->assertMutationAvailable($ownerTarget, $possession->idWorkspaceFolder);
        $this->assertEditablePossession($principal, $ownerTarget, $possession);
        $request = new FilePossessionOperationRequest($ownerTarget->ownerType, $ownerTarget->ownerId, $possession->id);

        match ($operation) {
            'trash' => $this->possessions->trash($request),
            'restore' => $this->possessions->restore($request),
            'release' => $this->possessions->release($request),
        };
    }

    /** @param Collection<int, WorkspaceFolder> $tree */
    private function releaseFolderTree(DriveWorkspaceTarget $target, Collection $tree): void
    {
        $folderIds = $tree->pluck('id');
        $possessions = StoredFilePossession::query()
            ->where($target->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant', $target->ownerId)
            ->where('storageArea', 'WORKSPACE')->where('state', 'TRASHED')->whereIn('idWorkspaceFolder', $folderIds)
            ->lockForUpdate()->get();
        foreach ($possessions as $possession) {
            $this->possessions->release(new FilePossessionOperationRequest($target->ownerType, $target->ownerId, $possession->id));
        }
        $tree->sortByDesc(fn (WorkspaceFolder $folder): int => $this->depth($folder))->each->delete();
    }

    /** @return Collection<int, WorkspaceFolder> */
    private function folderTree(DriveWorkspaceTarget $target, WorkspaceFolder $root, string $state): Collection
    {
        $ownerColumn = $target->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';
        $tree = collect([$root]);
        $parentIds = [$root->id];
        while ($parentIds !== []) {
            $children = WorkspaceFolder::query()->where($ownerColumn, $target->ownerId)->whereIn('idParentFolder', $parentIds)->where('state', $state)->lockForUpdate()->get();
            $tree = $tree->merge($children);
            $parentIds = $children->pluck('id')->all();
        }

        return $tree;
    }

    private function depth(WorkspaceFolder $folder): int
    {
        $depth = 0;
        while ($folder->idParentFolder !== null) {
            $depth++;
            $folder = WorkspaceFolder::query()->findOrFail($folder->idParentFolder);
        }

        return $depth;
    }

    private function assertDestinationIsNotInFolderTree(WorkspaceFolder $folder, ?int $destinationFolderId): void
    {
        while ($destinationFolderId !== null) {
            if ($destinationFolderId === $folder->id) {
                throw new DriveWorkspaceCommandException('DRIVE_INVALID_DESTINATION');
            }

            $destinationFolderId = WorkspaceFolder::query()->find($destinationFolderId)?->idParentFolder;
        }
    }

    private function assertEditableLocation(User $principal, DriveWorkspaceTarget $target, ?int $folderId): void
    {
        if ($folderId === null) {
            if ($this->canEditRoot($principal, $target)) {
                return;
            }
            throw new DriveWorkspaceCommandException('DRIVE_ACCESS_DENIED');
        }
        $this->assertEditableFolder($principal, $target, $this->folder($target, $folderId, 'ACTIVE'));
    }

    private function assertEditableFolder(User $principal, DriveWorkspaceTarget $target, WorkspaceFolder $folder): void
    {
        if ($folder->state !== 'ACTIVE') {
            if ($this->canManageTrashedItem($principal, $target)) {
                return;
            }
            throw new DriveWorkspaceCommandException('DRIVE_ACCESS_DENIED');
        }

        $resource = new ResourceReference($target->resourceType, $folder->id, $target->scope, $target->tenantId);
        if (! $this->authorization->check($principal, $target->resourceType.'.edit', $target->scope, $target->tenantId, $resource)->allowed) {
            throw new DriveWorkspaceCommandException('DRIVE_ACCESS_DENIED');
        }
    }

    private function assertEditablePossession(User $principal, DriveWorkspaceTarget $target, StoredFilePossession $possession): void
    {
        if ($possession->state !== 'ACTIVE') {
            if ($this->canManageTrashedItem($principal, $target)) {
                return;
            }
            throw new DriveWorkspaceCommandException('DRIVE_ACCESS_DENIED');
        }

        $this->assertEditableLocation($principal, $target, $possession->idWorkspaceFolder);
    }

    private function canManageTrashedItem(User $principal, DriveWorkspaceTarget $target): bool
    {
        return $target->scope->value === 'PERSONAL' && $target->ownerId === $principal->id && $this->canEditRoot($principal, $target)
            || $target->scope->value === 'TENANT' && $this->canEditRoot($principal, $target);
    }

    private function canEditRoot(User $principal, DriveWorkspaceTarget $target): bool
    {
        if ($this->authorization->check($principal, $target->resourceType.'.edit', $target->scope, $target->tenantId)->reasonCode === 'RESTRICTION_APPLIES') {
            return false;
        }

        return $target->scope->value === 'PERSONAL' && $target->ownerId === $principal->id
            || $target->scope->value === 'TENANT' && $this->tenantAdministrators->isActiveDirectAdministrator($principal, $target->tenantId);
    }

    private function targetForParent(User $principal, DriveWorkspaceTarget $target, ?int $parentFolderId): DriveWorkspaceTarget
    {
        if ($parentFolderId === null) {
            return $target;
        }

        return $this->targetForFolder($target, $this->activeFolder($target, $parentFolderId));
    }

    private function targetForFolder(DriveWorkspaceTarget $target, WorkspaceFolder $folder): DriveWorkspaceTarget
    {
        if ($target->scope->value === 'TENANT') {
            return $target;
        }
        if ($folder->idTenant !== null || $folder->idUser === null) {
            throw new DriveWorkspaceCommandException('DRIVE_LOCATION_NOT_FOUND');
        }

        return DriveWorkspaceTarget::personal($folder->idUser);
    }

    private function targetForPossession(DriveWorkspaceTarget $target, StoredFilePossession $possession): DriveWorkspaceTarget
    {
        if ($target->scope->value === 'TENANT') {
            return $target;
        }
        if ($possession->idTenant !== null || $possession->idUser === null) {
            throw new DriveWorkspaceCommandException('DRIVE_LOCATION_NOT_FOUND');
        }

        return DriveWorkspaceTarget::personal($possession->idUser);
    }

    private function activeFolder(DriveWorkspaceTarget $target, int $folderId): WorkspaceFolder
    {
        return $this->folder($target, $folderId, 'ACTIVE');
    }

    private function folder(DriveWorkspaceTarget $target, int $folderId, string $state): WorkspaceFolder
    {
        $query = WorkspaceFolder::query()->whereKey($folderId)->where('state', $state);
        if ($target->scope->value === 'TENANT') {
            $query->where('idTenant', $target->ownerId);
        } else {
            $query->whereNotNull('idUser')->whereNull('idTenant');
        }
        $folder = $query->lockForUpdate()->first();
        if ($folder === null) {
            throw new DriveWorkspaceCommandException('DRIVE_LOCATION_NOT_FOUND');
        }

        return $folder;
    }

    private function activePossession(DriveWorkspaceTarget $target, int $possessionId): StoredFilePossession
    {
        return $this->possession($target, $possessionId, 'ACTIVE');
    }

    private function possession(DriveWorkspaceTarget $target, int $possessionId, string $state): StoredFilePossession
    {
        $query = StoredFilePossession::query()->whereKey($possessionId)->where('storageArea', 'WORKSPACE')->where('state', $state);
        if ($target->scope->value === 'TENANT') {
            $query->where('idTenant', $target->ownerId);
        } else {
            $query->whereNotNull('idUser')->whereNull('idTenant');
        }
        $possession = $query->lockForUpdate()->first();
        if ($possession === null) {
            throw new DriveWorkspaceCommandException('DRIVE_LOCATION_NOT_FOUND');
        }

        return $possession;
    }

    private function lockWorkspace(DriveWorkspaceTarget $target): void
    {
        $model = $target->ownerType === FileStorageOwnerType::User ? User::class : Tenant::class;
        if ($model::query()->lockForUpdate()->find($target->ownerId) === null) {
            throw new DriveWorkspaceCommandException('DRIVE_WORKSPACE_UNAVAILABLE');
        }
    }
}
