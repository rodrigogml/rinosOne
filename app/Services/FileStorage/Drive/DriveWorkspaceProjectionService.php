<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceProjectionException;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\TenantAdministratorInvariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Projects only Drive data that the current principal can read in one resolved workspace. */
class DriveWorkspaceProjectionService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly TenantAdministratorInvariant $tenantAdministrators,
    ) {}

    /** @return array{folders: list<array<string, mixed>>} */
    public function tree(User $principal, DriveWorkspaceTarget $target): array
    {
        return ['folders' => $this->foldersFor($principal, $target, 'ACTIVE')
            ->map(fn (WorkspaceFolder $folder): array => $this->folderItem($principal, $target, $folder))
            ->values()
            ->all()];
    }

    /** @return array<string, mixed> */
    public function root(User $principal, DriveWorkspaceTarget $target): array
    {
        if (! $this->canReadRoot($principal, $target)) {
            throw new DriveWorkspaceProjectionException('DRIVE_ACCESS_DENIED');
        }

        return $this->location($principal, $target, null);
    }

    /** @return array<string, mixed> */
    public function folder(User $principal, DriveWorkspaceTarget $target, int $folderId): array
    {
        $folder = $this->folderQuery($target)->whereKey($folderId)->where('state', 'ACTIVE')->first();
        if ($folder === null) {
            throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
        }
        if (! $this->capabilities($principal, $target, $folder)['read']) {
            throw new DriveWorkspaceProjectionException('DRIVE_ACCESS_DENIED');
        }

        return $this->location($principal, $target, $folder);
    }

    /** @return array<string, mixed> */
    public function trash(User $principal, DriveWorkspaceTarget $target): array
    {
        $folders = $this->folderQuery($target)->where('state', 'TRASHED')->orderBy('idParentFolder')->orderBy('displayName')->get()
            ->filter(fn (WorkspaceFolder $folder): bool => $this->canReadTrashedFolder($principal, $target, $folder))
            ->map(fn (WorkspaceFolder $folder): array => [...$this->trashedFolderItem($principal, $target, $folder), 'purgeAfter' => $folder->purgeAfter?->toISOString()])
            ->values()
            ->all();
        $files = $this->possessionsFor($target, 'TRASHED')
            ->get()
            ->filter(fn (StoredFilePossession $possession): bool => $this->canReadPossession($principal, $target, $possession))
            ->map(fn (StoredFilePossession $possession): array => [...$this->fileItem($principal, $target, $possession), 'purgeAfter' => $possession->purgeAfter?->toISOString()])
            ->values()
            ->all();

        return [
            'location' => ['kind' => 'trash', 'id' => null, 'displayName' => 'Lixeira', 'parentFolderId' => null],
            'breadcrumbs' => [],
            'folders' => $folders,
            'files' => $files,
            'capabilities' => ['read' => $this->canReadRoot($principal, $target), 'edit' => false, 'trash' => false],
            'usage' => $this->usage($target),
            'purgeAfter' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function details(User $principal, DriveWorkspaceTarget $target, string $itemType, int $itemId): array
    {
        if ($itemType === 'folder') {
            $folder = $this->folderQuery($target)->whereKey($itemId)->where('state', 'ACTIVE')->first();
            if ($folder === null) {
                throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
            }
            if (! $this->capabilities($principal, $target, $folder)['read']) {
                throw new DriveWorkspaceProjectionException('DRIVE_ACCESS_DENIED');
            }

            return [
                'item' => $this->folderItem($principal, $target, $folder),
                'location' => $this->locationReference($folder),
                'capabilities' => $this->capabilities($principal, $target, $folder),
                'metadata' => [],
            ];
        }

        $possession = $this->possessionsFor($target, 'ACTIVE')->whereKey($itemId)->first();
        if ($possession === null) {
            throw new DriveWorkspaceProjectionException('DRIVE_LOCATION_NOT_FOUND');
        }
        if (! $this->canReadPossession($principal, $target, $possession)) {
            throw new DriveWorkspaceProjectionException('DRIVE_ACCESS_DENIED');
        }

        return [
            'item' => $this->fileItem($principal, $target, $possession),
            'location' => $possession->idWorkspaceFolder === null
                ? $this->rootReference($target)
                : $this->locationReference(WorkspaceFolder::query()->findOrFail($possession->idWorkspaceFolder)),
            'capabilities' => $this->possessionCapabilities($principal, $target, $possession),
            'metadata' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function location(User $principal, DriveWorkspaceTarget $target, ?WorkspaceFolder $location): array
    {
        $folders = $this->folderQuery($target)
            ->where('state', 'ACTIVE')
            ->where('idParentFolder', $location?->id)
            ->orderBy('displayName')
            ->get()
            ->filter(fn (WorkspaceFolder $folder): bool => $this->capabilities($principal, $target, $folder)['read'])
            ->map(fn (WorkspaceFolder $folder): array => $this->folderItem($principal, $target, $folder))
            ->values()
            ->all();
        $files = $this->possessionsFor($target, 'ACTIVE')
            ->where('idWorkspaceFolder', $location?->id)
            ->get()
            ->filter(fn (StoredFilePossession $possession): bool => $this->canReadPossession($principal, $target, $possession))
            ->map(fn (StoredFilePossession $possession): array => $this->fileItem($principal, $target, $possession))
            ->values()
            ->all();

        return [
            'location' => $location === null
                ? $this->rootReference($target)
                : $this->locationReference($location),
            'breadcrumbs' => $location === null ? [] : $this->breadcrumbs($principal, $target, $location),
            'folders' => $folders,
            'files' => $files,
            'capabilities' => $location === null ? ['read' => true, 'edit' => $this->canEditRoot($principal, $target), 'trash' => false] : $this->capabilities($principal, $target, $location),
            'usage' => $this->usage($target),
        ];
    }

    /** @return Collection<int, WorkspaceFolder> */
    private function foldersFor(User $principal, DriveWorkspaceTarget $target, string $state): Collection
    {
        return $this->folderQuery($target)->where('state', $state)->orderBy('idParentFolder')->orderBy('displayName')->get()
            ->filter(fn (WorkspaceFolder $folder): bool => $this->capabilities($principal, $target, $folder)['read'])
            ->values();
    }

    /** @return Builder<WorkspaceFolder> */
    private function folderQuery(DriveWorkspaceTarget $target): Builder
    {
        if ($target->scope->value === 'PERSONAL') {
            return WorkspaceFolder::query()->whereNotNull('idUser')->whereNull('idTenant');
        }

        return WorkspaceFolder::query()->where($target->ownerType->value === 'USER' ? 'idUser' : 'idTenant', $target->ownerId);
    }

    /** @return Builder<StoredFilePossession> */
    private function possessionsFor(DriveWorkspaceTarget $target, string $state): Builder
    {
        $query = StoredFilePossession::query()
            ->with('currentVersion.content')
            ->where('storageArea', 'WORKSPACE')
            ->where('state', $state)
            ->orderBy('displayName');

        if ($target->scope->value === 'PERSONAL') {
            return $query->whereNotNull('idUser')->whereNull('idTenant');
        }

        return $query->where('idTenant', $target->ownerId);
    }

    /** @return array{read: bool, edit: bool, trash: bool} */
    private function capabilities(User $principal, DriveWorkspaceTarget $target, WorkspaceFolder $folder): array
    {
        $resource = new ResourceReference($target->resourceType, $folder->id, $target->scope, $target->tenantId);
        $edit = $this->authorization->check($principal, $target->resourceType.'.edit', $target->scope, $target->tenantId, $resource)->allowed;
        $read = $edit || $this->authorization->check($principal, $target->resourceType.'.read', $target->scope, $target->tenantId, $resource)->allowed;

        return ['read' => $read, 'edit' => $edit, 'trash' => $edit];
    }

    private function canReadPossession(User $principal, DriveWorkspaceTarget $target, StoredFilePossession $possession): bool
    {
        if ($possession->state === 'TRASHED') {
            return $this->canReadTrashedPossession($principal, $target, $possession);
        }

        if ($possession->idWorkspaceFolder === null) {
            if ($target->scope->value === 'PERSONAL') {
                return $possession->idUser === $principal->id && $this->canReadRoot($principal, $target);
            }

            return $target->scope->value === 'TENANT' && $this->canReadRoot($principal, $target);
        }

        $folder = WorkspaceFolder::query()->find($possession->idWorkspaceFolder);

        return $folder !== null && $this->capabilities($principal, $target, $folder)['read'];
    }

    private function canReadTrashedFolder(User $principal, DriveWorkspaceTarget $target, WorkspaceFolder $folder): bool
    {
        if (! $this->canReadRoot($principal, $target)) {
            return false;
        }

        return $target->scope->value === 'TENANT' || $folder->idUser === $principal->id;
    }

    private function canReadTrashedPossession(User $principal, DriveWorkspaceTarget $target, StoredFilePossession $possession): bool
    {
        if (! $this->canReadRoot($principal, $target)) {
            return false;
        }

        return $target->scope->value === 'TENANT' || $possession->idUser === $principal->id;
    }

    /** @return array{read: bool, edit: bool, trash: bool} */
    private function possessionCapabilities(User $principal, DriveWorkspaceTarget $target, StoredFilePossession $possession): array
    {
        if ($possession->idWorkspaceFolder === null) {
            $edit = $this->canEditRoot($principal, $target);

            return ['read' => $this->canReadRoot($principal, $target), 'edit' => $edit, 'trash' => $edit];
        }

        $folder = WorkspaceFolder::query()->find($possession->idWorkspaceFolder);

        return $folder === null ? ['read' => false, 'edit' => false, 'trash' => false] : $this->capabilities($principal, $target, $folder);
    }

    private function canReadRoot(User $principal, DriveWorkspaceTarget $target): bool
    {
        if ($this->authorization->check($principal, $target->resourceType.'.read', $target->scope, $target->tenantId)->reasonCode === 'RESTRICTION_APPLIES') {
            return false;
        }

        return $target->scope->value === 'PERSONAL'
            || $this->tenantAdministrators->isActiveDirectAdministrator($principal, $target->tenantId);
    }

    private function canEditRoot(User $principal, DriveWorkspaceTarget $target): bool
    {
        if ($this->authorization->check($principal, $target->resourceType.'.edit', $target->scope, $target->tenantId)->reasonCode === 'RESTRICTION_APPLIES') {
            return false;
        }

        return $target->scope->value === 'PERSONAL'
            || $this->tenantAdministrators->isActiveDirectAdministrator($principal, $target->tenantId);
    }

    /** @return list<array<string, mixed>> */
    private function breadcrumbs(User $principal, DriveWorkspaceTarget $target, WorkspaceFolder $folder): array
    {
        $breadcrumbs = [];
        while ($folder !== null && $this->capabilities($principal, $target, $folder)['read']) {
            array_unshift($breadcrumbs, $this->locationReference($folder));
            $folder = $folder->idParentFolder === null ? null : WorkspaceFolder::query()->find($folder->idParentFolder);
        }

        return $breadcrumbs;
    }

    /** @return array<string, mixed> */
    private function folderItem(User $principal, DriveWorkspaceTarget $target, WorkspaceFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'kind' => 'folder',
            'displayName' => $folder->displayName,
            'parentFolderId' => $folder->idParentFolder,
            'logicalSizeBytes' => null,
            'detectedMimeType' => null,
            'modifiedAt' => $folder->updatedAt?->toISOString(),
            'capabilities' => $this->capabilities($principal, $target, $folder),
        ];
    }

    /** @return array<string, mixed> */
    private function trashedFolderItem(User $principal, DriveWorkspaceTarget $target, WorkspaceFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'kind' => 'folder',
            'displayName' => $folder->displayName,
            'parentFolderId' => $folder->idParentFolder,
            'logicalSizeBytes' => null,
            'detectedMimeType' => null,
            'modifiedAt' => $folder->updatedAt?->toISOString(),
            'capabilities' => ['read' => $this->canReadTrashedFolder($principal, $target, $folder), 'edit' => false, 'trash' => false],
        ];
    }

    /** @return array<string, mixed> */
    private function fileItem(User $principal, DriveWorkspaceTarget $target, StoredFilePossession $possession): array
    {
        return [
            'id' => $possession->id,
            'kind' => 'file',
            'displayName' => $possession->displayName,
            'parentFolderId' => $possession->idWorkspaceFolder,
            'logicalSizeBytes' => $possession->logicalSizeBytes,
            'detectedMimeType' => $possession->currentVersion?->content?->detectedMimeType,
            'modifiedAt' => $possession->updatedAt?->toISOString(),
            'capabilities' => $this->possessionCapabilities($principal, $target, $possession),
        ];
    }

    /** @return array{kind: string, id: int, displayName: string, parentFolderId: ?int} */
    private function locationReference(WorkspaceFolder $folder): array
    {
        return ['kind' => 'folder', 'id' => $folder->id, 'displayName' => $folder->displayName, 'parentFolderId' => $folder->idParentFolder];
    }

    /** @return array{kind: string, id: null, displayName: string, parentFolderId: null} */
    private function rootReference(DriveWorkspaceTarget $target): array
    {
        return [
            'kind' => 'root',
            'id' => null,
            'displayName' => $target->scope->value === 'PERSONAL' ? 'Meus arquivos' : 'Arquivos da organização',
            'parentFolderId' => null,
        ];
    }

    /** @return array{workspaceBytes: int, systemManagedBytes: int, trashBytes: int, totalBytes: int} */
    private function usage(DriveWorkspaceTarget $target): array
    {
        $usage = StoredFileOwnerUsage::query()->where($target->ownerType->value === 'USER' ? 'idUser' : 'idTenant', $target->ownerId)->first();

        return [
            'workspaceBytes' => (int) ($usage?->workspaceBytes ?? 0),
            'systemManagedBytes' => (int) ($usage?->systemManagedBytes ?? 0),
            'trashBytes' => (int) ($usage?->trashBytes ?? 0),
            'totalBytes' => (int) ($usage?->totalBytes ?? 0),
        ];
    }
}
