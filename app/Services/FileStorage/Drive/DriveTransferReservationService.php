<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferReservationSide;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\FileStorage\WorkspaceTransferReservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Acquires persistent branch leases in a deterministic order and detects every active-tree overlap. */
class DriveTransferReservationService
{
    /**
     * @param  array{target: DriveWorkspaceTarget, rootFolderId: ?int, side: WorkspaceTransferReservationSide}  $source
     * @param  array{target: DriveWorkspaceTarget, rootFolderId: ?int, side: WorkspaceTransferReservationSide}  $destination
     */
    public function acquire(WorkspaceTransfer $transfer, array $source, array $destination, ?Carbon $now = null): void
    {
        $this->acquireAll($transfer, [$source, $destination], $now);
    }

    /** @param list<array{target: DriveWorkspaceTarget, rootFolderId: ?int, side: WorkspaceTransferReservationSide}> $leases */
    public function acquireAll(WorkspaceTransfer $transfer, array $leases, ?Carbon $now = null): void
    {
        DB::transaction(function () use ($transfer, $leases, $now): void {
            $current = $now ?? now();
            usort($leases, fn (array $left, array $right): int => $this->sortKey($left) <=> $this->sortKey($right));
            foreach ($leases as $lease) {
                $this->lockContext($lease['target']);
            }
            foreach ($leases as $lease) {
                $this->assertAvailable($lease['target'], $lease['rootFolderId'], $current);
            }
            foreach ($leases as $lease) {
                WorkspaceTransferReservation::query()->create([
                    'idWorkspaceTransfer' => $transfer->id,
                    'side' => $lease['side'],
                    'workspaceScope' => $lease['target']->scope->value,
                    'idTenant' => $lease['target']->tenantId,
                    'rootFolderId' => $lease['rootFolderId'],
                    'leaseExpiresAt' => $transfer->leaseExpiresAt ?? $current->copy()->addMinutes((int) config('file-storage.workspaceTransfer.leaseMinutes', 15)),
                ]);
            }
        });
    }

    public function assertMutationAvailable(DriveWorkspaceTarget $target, ?int $folderId, ?Carbon $now = null): void
    {
        $current = $now ?? now();
        $query = WorkspaceTransferReservation::query()
            ->where('workspaceScope', $target->scope->value)
            ->where('idTenant', $target->tenantId)
            ->where('leaseExpiresAt', '>', $current);
        foreach ($query->get() as $reservation) {
            if ($this->intersects($target, $folderId, $reservation->rootFolderId)) {
                throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_IN_PROGRESS');
            }
        }
    }

    private function assertAvailable(DriveWorkspaceTarget $target, ?int $rootFolderId, Carbon $now): void
    {
        $this->assertMutationAvailable($target, $rootFolderId, $now);
    }

    private function intersects(DriveWorkspaceTarget $target, ?int $leftRootId, ?int $rightRootId): bool
    {
        if ($leftRootId === null || $rightRootId === null) {
            return true;
        }

        return $this->isDescendantOrSame($target, $leftRootId, $rightRootId)
            || $this->isDescendantOrSame($target, $rightRootId, $leftRootId);
    }

    private function isDescendantOrSame(DriveWorkspaceTarget $target, int $candidateId, int $ancestorId): bool
    {
        $current = $candidateId;
        while ($current !== null) {
            if ($current === $ancestorId) {
                return true;
            }
            $query = WorkspaceFolder::query()->whereKey($current);
            if ($target->scope->value === 'PERSONAL') {
                $query->whereNotNull('idUser')->whereNull('idTenant');
            } else {
                $query->where('idTenant', $target->ownerId);
            }
            $current = $query->value('idParentFolder');
        }

        return false;
    }

    private function lockContext(DriveWorkspaceTarget $target): void
    {
        // Locks the ordered set of folders instead of retaining a long transaction.
        $query = WorkspaceFolder::query()->select('id')->orderBy('id')->lockForUpdate();
        if ($target->scope->value === 'PERSONAL') {
            $query->whereNotNull('idUser')->whereNull('idTenant');
        } else {
            $query->where('idTenant', $target->ownerId);
        }
        $query->get();
    }

    /** @param array{target: DriveWorkspaceTarget, rootFolderId: ?int, side: WorkspaceTransferReservationSide} $lease */
    private function sortKey(array $lease): string
    {
        $target = $lease['target'];

        return sprintf('%s:%020d:%020d', $target->scope->value, $target->tenantId ?? 0, $lease['rootFolderId'] ?? 0);
    }
}
