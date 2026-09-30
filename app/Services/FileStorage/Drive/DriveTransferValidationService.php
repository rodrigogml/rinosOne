<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;

/** Validates a complete logical transfer request before it can reserve either workspace branch. */
class DriveTransferValidationService
{
    public function __construct(private readonly DriveWorkspaceProjectionService $projections) {}

    /** @param list<array{type: string, id: int}> $items */
    public function validate(User $principal, DriveWorkspaceTarget $source, DriveWorkspaceTarget $destination, array $items, WorkspaceTransferMode $mode, ?int $destinationFolderId): void
    {
        $maximumItems = (int) config('file-storage.workspaceTransfer.maximumItems', 100);
        if ($items === [] || count($items) > $maximumItems || count($items) !== count(array_unique(array_map(fn (array $item): string => $item['type'].':'.$item['id'], $items)))) {
            throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_LIMIT_EXCEEDED');
        }
        if ($maximumItems < 1 || (int) config('file-storage.workspaceTransfer.maximumTreeDepth', 32) < 1) {
            throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_INVALID');
        }
        if ($source->scope === $destination->scope && $source->ownerId === $destination->ownerId && $destinationFolderId === null) {
            throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_INVALID');
        }

        foreach ($items as $item) {
            if (! in_array($item['type'], ['folder', 'file'], true) || $item['id'] < 1) {
                throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_INVALID');
            }
            $this->projections->details($principal, $source, $item['type'], $item['id']);
            if ($item['type'] === 'folder' && $this->depthBelow($source, $item['id']) > (int) config('file-storage.workspaceTransfer.maximumTreeDepth', 32)) {
                throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_LIMIT_EXCEEDED');
            }
            if ($item['type'] === 'folder'
                && $source->scope === $destination->scope
                && $source->ownerId === $destination->ownerId
                && $destinationFolderId !== null
                && $this->isDescendantOrSame($source, $item['id'], $destinationFolderId)) {
                throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_INVALID');
            }
        }

        // The mode is deliberately accepted here even for an in-drive transfer: a caller may
        // explicitly choose Copy instead of Move. The execution phase owns the semantic change.
        if (! in_array($mode, [WorkspaceTransferMode::Copy, WorkspaceTransferMode::Move], true)) {
            throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_INVALID');
        }
        if ($destinationFolderId !== null) {
            $location = $this->projections->folder($principal, $destination, $destinationFolderId);
            if (! $location['capabilities']['edit']) {
                throw new DriveWorkspaceCommandException('DRIVE_ACCESS_DENIED');
            }
        } else {
            $root = $this->projections->root($principal, $destination);
            if (! $root['capabilities']['edit']) {
                throw new DriveWorkspaceCommandException('DRIVE_ACCESS_DENIED');
            }
        }
    }

    private function depthBelow(DriveWorkspaceTarget $target, int $rootFolderId): int
    {
        $pending = [[$rootFolderId, 0]];
        $deepest = 0;
        while ($pending !== []) {
            [$folderId, $depth] = array_pop($pending);
            $deepest = max($deepest, $depth);
            $query = WorkspaceFolder::query()->where('idParentFolder', $folderId)->where('state', 'ACTIVE');
            if ($target->scope->value === 'PERSONAL') {
                $query->whereNotNull('idUser')->whereNull('idTenant');
            } else {
                $query->where('idTenant', $target->ownerId);
            }
            foreach ($query->pluck('id') as $childId) {
                $pending[] = [(int) $childId, $depth + 1];
            }
        }

        return $deepest;
    }

    private function isDescendantOrSame(DriveWorkspaceTarget $target, int $sourceFolderId, int $destinationFolderId): bool
    {
        $current = $destinationFolderId;
        while ($current !== null) {
            if ($current === $sourceFolderId) {
                return true;
            }
            $query = WorkspaceFolder::query()->whereKey($current)->where('state', 'ACTIVE');
            if ($target->scope->value === 'PERSONAL') {
                $query->whereNotNull('idUser')->whereNull('idTenant');
            } else {
                $query->where('idTenant', $target->ownerId);
            }
            $current = $query->value('idParentFolder');
        }

        return false;
    }
}
