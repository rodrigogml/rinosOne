<?php

namespace App\Services\FileStorage;

use App\Contracts\FileStorage\V1\FilePossessionOperationRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\FileStorage\Exception\FileStorageVersionException;
use App\Domain\FileStorage\Retention\FileStorageRetentionPolicy;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceFolderService
{
    public function __construct(
        private readonly FileStoragePossessionLifecycleService $possessionLifecycleService,
        private readonly FileStorageRetentionPolicy $retentionPolicy,
    ) {}

    /** Creates an active folder in the requested user or tenant workspace. */
    public function create(FileStorageOwnerType $ownerType, int $ownerId, string $displayName, ?int $parentFolderId = null): WorkspaceFolder
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $displayName, $parentFolderId): WorkspaceFolder {
            $this->assertFolderInput($ownerId, $displayName);
            $this->lockWorkspace($ownerType, $ownerId);
            $this->lockParent($ownerType, $ownerId, $parentFolderId, 'ACTIVE');

            return WorkspaceFolder::query()->create([
                $this->ownerColumn($ownerType) => $ownerId,
                'idParentFolder' => $parentFolderId,
                'displayName' => trim($displayName),
                'state' => 'ACTIVE',
            ]);
        });
    }

    /** Moves an active folder without allowing a cross-workspace or descendant parent. */
    public function move(FileStorageOwnerType $ownerType, int $ownerId, int $folderId, ?int $parentFolderId): WorkspaceFolder
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $folderId, $parentFolderId): WorkspaceFolder {
            $this->lockWorkspace($ownerType, $ownerId);
            $folder = $this->resolveFolder($ownerType, $ownerId, $folderId, 'ACTIVE');
            $this->lockParent($ownerType, $ownerId, $parentFolderId, 'ACTIVE');

            $folder->forceFill(['idParentFolder' => $parentFolderId])->save();

            return $folder;
        });
    }

    /** Renames an active folder subject to its workspace sibling uniqueness rule. */
    public function rename(FileStorageOwnerType $ownerType, int $ownerId, int $folderId, string $displayName): WorkspaceFolder
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $folderId, $displayName): WorkspaceFolder {
            $this->assertFolderInput($ownerId, $displayName);
            $this->lockWorkspace($ownerType, $ownerId);
            $folder = $this->resolveFolder($ownerType, $ownerId, $folderId, 'ACTIVE');
            $folder->forceFill(['displayName' => trim($displayName)])->save();

            return $folder;
        });
    }

    /** Lists the folders in one workspace without exposing another workspace's tree. */
    public function listTree(FileStorageOwnerType $ownerType, int $ownerId, string $state = 'ACTIVE'): Collection
    {
        if ($ownerId < 1 || ! in_array($state, ['ACTIVE', 'TRASHED'], true)) {
            throw new FileStorageVersionException('The workspace folder list request is invalid.');
        }

        return WorkspaceFolder::query()
            ->where($this->ownerColumn($ownerType), $ownerId)
            ->where('state', $state)
            ->orderBy('idParentFolder')
            ->orderBy('displayName')
            ->get();
    }

    /** Moves a folder, all descendants and all contained workspace possessions to the trash atomically. */
    public function trash(FileStorageOwnerType $ownerType, int $ownerId, int $folderId): void
    {
        DB::transaction(function () use ($ownerType, $ownerId, $folderId): void {
            $this->lockWorkspace($ownerType, $ownerId);
            $root = $this->resolveFolder($ownerType, $ownerId, $folderId, 'ACTIVE');
            $folders = $this->lockTree($ownerType, $ownerId, $root);
            $possessions = $this->lockPossessions($folders, 'ACTIVE');

            foreach ($possessions as $possession) {
                $this->possessionLifecycleService->trash(new FilePossessionOperationRequest($ownerType, $ownerId, $possession->id));
            }

            $trashedAt = now()->toImmutable();
            $purgeAfter = $this->retentionPolicy->purgeAfter($trashedAt);
            foreach ($folders as $folder) {
                $folder->forceFill([
                    'state' => 'TRASHED',
                    'trashedAt' => $trashedAt,
                    'purgeAfter' => $purgeAfter,
                ])->save();
            }
        });
    }

    /** Restores a complete folder tree only while every folder and possession remains recoverable. */
    public function restore(FileStorageOwnerType $ownerType, int $ownerId, int $folderId): void
    {
        DB::transaction(function () use ($ownerType, $ownerId, $folderId): void {
            $this->lockWorkspace($ownerType, $ownerId);
            $root = $this->resolveFolder($ownerType, $ownerId, $folderId, 'TRASHED');
            $folders = $this->lockTree($ownerType, $ownerId, $root);
            $possessions = $this->lockPossessions($folders, 'TRASHED');

            if ($folders->contains(fn (WorkspaceFolder $folder): bool => $folder->purgeAfter === null || $folder->purgeAfter->isPast())
                || $possessions->contains(fn (StoredFilePossession $possession): bool => $possession->purgeAfter === null || $possession->purgeAfter->isPast())) {
                throw new FileStorageVersionException('The workspace folder set can no longer be restored from the trash.');
            }

            foreach ($possessions as $possession) {
                $this->possessionLifecycleService->restore(new FilePossessionOperationRequest($ownerType, $ownerId, $possession->id));
            }

            foreach ($folders as $folder) {
                $folder->forceFill([
                    'state' => 'ACTIVE',
                    'trashedAt' => null,
                    'purgeAfter' => null,
                ])->save();
            }
        });
    }

    /** @return Collection<int, WorkspaceFolder> */
    private function lockTree(FileStorageOwnerType $ownerType, int $ownerId, WorkspaceFolder $root): Collection
    {
        $ownerColumn = $this->ownerColumn($ownerType);
        $folders = new Collection([$root]);
        $parentIds = [$root->id];

        while ($parentIds !== []) {
            $children = WorkspaceFolder::query()
                ->where($ownerColumn, $ownerId)
                ->whereIn('idParentFolder', $parentIds)
                ->lockForUpdate()
                ->get();
            $folders = $folders->merge($children);
            $parentIds = $children->pluck('id')->all();
        }

        if ($folders->contains(fn (WorkspaceFolder $folder): bool => $folder->state !== $root->state)) {
            throw new FileStorageVersionException('The workspace folder tree is not in a consistent lifecycle state.');
        }

        return $folders;
    }

    /** @param Collection<int, WorkspaceFolder> $folders @return Collection<int, StoredFilePossession> */
    private function lockPossessions(Collection $folders, string $state): Collection
    {
        return StoredFilePossession::query()
            ->where('storageArea', 'WORKSPACE')
            ->where('state', $state)
            ->whereIn('idWorkspaceFolder', $folders->pluck('id'))
            ->lockForUpdate()
            ->get();
    }

    private function lockParent(FileStorageOwnerType $ownerType, int $ownerId, ?int $parentFolderId, string $state): void
    {
        if ($parentFolderId !== null) {
            $this->resolveFolder($ownerType, $ownerId, $parentFolderId, $state);
        }
    }

    private function resolveFolder(FileStorageOwnerType $ownerType, int $ownerId, int $folderId, string $state): WorkspaceFolder
    {
        if ($ownerId < 1 || $folderId < 1) {
            throw new FileStorageVersionException('The workspace folder operation request is invalid.');
        }

        $folder = WorkspaceFolder::query()
            ->whereKey($folderId)
            ->where($this->ownerColumn($ownerType), $ownerId)
            ->where('state', $state)
            ->lockForUpdate()
            ->first();

        if ($folder === null) {
            throw new FileStorageVersionException('The workspace folder does not exist or is not available to this owner.');
        }

        return $folder;
    }

    private function assertFolderInput(int $ownerId, string $displayName): void
    {
        if ($ownerId < 1 || trim($displayName) === '') {
            throw new FileStorageVersionException('The workspace folder request is invalid.');
        }
    }

    private function lockWorkspace(FileStorageOwnerType $ownerType, int $ownerId): void
    {
        $model = $ownerType === FileStorageOwnerType::User ? User::class : Tenant::class;

        if ($ownerId < 1 || $model::query()->lockForUpdate()->find($ownerId) === null) {
            throw new FileStorageVersionException('The workspace owner does not exist.');
        }
    }

    private function ownerColumn(FileStorageOwnerType $ownerType): string
    {
        return $ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';
    }
}
