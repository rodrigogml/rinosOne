<?php

namespace App\Services\FileStorage\Drive;

use App\Contracts\FileStorage\V1\FilePossessionOperationRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\User;
use App\Services\FileStorage\FileStoragePossessionLifecycleService;
use Illuminate\Support\Facades\DB;

/** Executes a persisted logical transfer without copying storage objects or physical bytes. */
class WorkspaceTransferExecutionService
{
    public function __construct(
        private readonly DriveTransferValidationService $validation,
        private readonly WorkspaceTransferLifecycleService $lifecycle,
        private readonly DriveWorkspaceNameResolver $names,
        private readonly FileStoragePossessionLifecycleService $possessions,
    ) {}

    public function execute(User $principal, WorkspaceTransfer $transfer): WorkspaceTransfer
    {
        $transfer = $this->lifecycle->begin($transfer);
        try {
            return DB::transaction(function () use ($principal, $transfer): WorkspaceTransfer {
                $source = $this->sourceTarget($transfer);
                $destination = $this->destinationTarget($transfer);
                $destinationFolderId = $this->destinationFolderId($transfer);
                $this->validation->validate($principal, $source, $destination, $transfer->selectionManifest, $transfer->mode, $destinationFolderId);
                foreach ($transfer->selectionManifest as $item) {
                    if ($item['type'] === 'folder') {
                        $this->copyFolderTree($source, $destination, (int) $item['id'], $destinationFolderId);
                    } else {
                        $this->copyPossession($source, $destination, (int) $item['id'], $destinationFolderId);
                    }
                    $transfer->increment('processedItems');
                }
                if ($transfer->mode === WorkspaceTransferMode::Move) {
                    foreach ($transfer->selectionManifest as $item) {
                        $item['type'] === 'folder' ? $this->releaseFolderTree($source, (int) $item['id']) : $this->releasePossession($source, (int) $item['id']);
                    }
                }

                return $this->lifecycle->complete($transfer->refresh());
            });
        } catch (\Throwable $exception) {
            $this->lifecycle->fail($transfer, $exception instanceof DriveWorkspaceCommandException ? $exception->reasonCode : 'DRIVE_TRANSFER_FAILED');
            throw $exception;
        }
    }

    private function copyFolderTree(DriveWorkspaceTarget $source, DriveWorkspaceTarget $destination, int $sourceFolderId, ?int $destinationParentId): void
    {
        $folder = $this->folder($source, $sourceFolderId);
        $copy = WorkspaceFolder::query()->create([
            $destination->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant' => $destination->ownerId,
            'idParentFolder' => $destinationParentId,
            'displayName' => $this->names->resolve($destination, $destinationParentId, $folder->displayName),
            'state' => 'ACTIVE',
        ]);
        foreach ($this->children($source, $folder->id) as $child) {
            $this->copyFolderTree($source, $destination, $child->id, $copy->id);
        }
        foreach ($this->filesInFolder($source, $folder->id) as $possession) {
            $this->copyPossession($source, $destination, $possession->id, $copy->id);
        }
    }

    private function copyPossession(DriveWorkspaceTarget $source, DriveWorkspaceTarget $destination, int $possessionId, ?int $destinationFolderId): void
    {
        $sourcePossession = $this->possession($source, $possessionId);
        $copy = StoredFilePossession::query()->create([
            'idFile' => $sourcePossession->idFile,
            'idCurrentFileVersion' => $sourcePossession->idCurrentFileVersion,
            $destination->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant' => $destination->ownerId,
            'idWorkspaceFolder' => $destinationFolderId,
            'storageArea' => 'WORKSPACE',
            'displayName' => $this->names->resolve($destination, $destinationFolderId, $sourcePossession->displayName, null, null),
            'state' => 'ACTIVE',
            'logicalSizeBytes' => $sourcePossession->logicalSizeBytes,
        ]);
        $this->adjustUsage($destination, $copy->logicalSizeBytes);
    }

    private function releasePossession(DriveWorkspaceTarget $source, int $possessionId): void
    {
        $this->possessions->release(new FilePossessionOperationRequest($source->ownerType, $source->ownerId, $possessionId));
    }

    private function releaseFolderTree(DriveWorkspaceTarget $source, int $folderId): void
    {
        $folder = $this->folder($source, $folderId);
        foreach ($this->children($source, $folder->id) as $child) {
            $this->releaseFolderTree($source, $child->id);
        }
        foreach ($this->filesInFolder($source, $folder->id) as $possession) {
            $this->releasePossession($source, $possession->id);
        }
        $folder->delete();
    }

    private function sourceTarget(WorkspaceTransfer $transfer): DriveWorkspaceTarget
    {
        return $transfer->sourceScope === 'PERSONAL' ? DriveWorkspaceTarget::personal((int) $transfer->sourceUserId) : DriveWorkspaceTarget::work((int) $transfer->sourceTenantId);
    }

    private function destinationTarget(WorkspaceTransfer $transfer): DriveWorkspaceTarget
    {
        return $transfer->destinationScope === 'PERSONAL' ? DriveWorkspaceTarget::personal((int) $transfer->destinationUserId) : DriveWorkspaceTarget::work((int) $transfer->destinationTenantId);
    }

    private function destinationFolderId(WorkspaceTransfer $transfer): ?int
    {
        return $transfer->destinationFolderId;
    }

    private function folder(DriveWorkspaceTarget $target, int $id): WorkspaceFolder
    {
        return WorkspaceFolder::query()->whereKey($id)->where($target->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant', $target->ownerId)->where('state', 'ACTIVE')->lockForUpdate()->firstOrFail();
    }

    private function possession(DriveWorkspaceTarget $target, int $id): StoredFilePossession
    {
        return StoredFilePossession::query()->whereKey($id)->where($target->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant', $target->ownerId)->where('storageArea', 'WORKSPACE')->where('state', 'ACTIVE')->lockForUpdate()->firstOrFail();
    }

    /** @return iterable<WorkspaceFolder> */
    private function children(DriveWorkspaceTarget $target, int $parentId): iterable
    {
        return WorkspaceFolder::query()->where($target->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant', $target->ownerId)->where('idParentFolder', $parentId)->where('state', 'ACTIVE')->lockForUpdate()->get();
    }

    /** @return iterable<StoredFilePossession> */
    private function filesInFolder(DriveWorkspaceTarget $target, int $folderId): iterable
    {
        return StoredFilePossession::query()->where($target->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant', $target->ownerId)->where('idWorkspaceFolder', $folderId)->where('storageArea', 'WORKSPACE')->where('state', 'ACTIVE')->lockForUpdate()->get();
    }

    private function adjustUsage(DriveWorkspaceTarget $target, int $bytes): void
    {
        $column = $target->ownerType === FileStorageOwnerType::User ? 'idUser' : 'idTenant';
        $usage = StoredFileOwnerUsage::query()->firstOrCreate([$column => $target->ownerId], ['workspaceBytes' => 0, 'systemManagedBytes' => 0, 'trashBytes' => 0, 'totalBytes' => 0]);
        $usage = StoredFileOwnerUsage::query()->whereKey($usage->id)->lockForUpdate()->firstOrFail();
        $usage->increment('workspaceBytes', $bytes);
        $usage->increment('totalBytes', $bytes);
    }
}
