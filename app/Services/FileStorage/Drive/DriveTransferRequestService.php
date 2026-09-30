<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Domain\FileStorage\Drive\WorkspaceTransferReservationSide;
use App\Domain\FileStorage\Drive\WorkspaceTransferState;
use App\Jobs\FileStorage\ProcessWorkspaceTransfer;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Creates a durable transfer and every required branch reservation in one database commit. */
class DriveTransferRequestService
{
    public function __construct(
        private readonly DriveTransferValidationService $validation,
        private readonly DriveTransferReservationService $reservations,
    ) {}

    /** @param list<array{type: string, id: int}> $items */
    public function request(User $principal, DriveWorkspaceTarget $source, DriveWorkspaceTarget $destination, array $items, WorkspaceTransferMode $mode, ?int $destinationFolderId, ?string $idempotencyKey = null, ?string $correlationId = null): WorkspaceTransfer
    {
        $this->validation->validate($principal, $source, $destination, $items, $mode, $destinationFolderId);

        $transfer = DB::transaction(function () use ($principal, $source, $destination, $items, $mode, $destinationFolderId, $idempotencyKey, $correlationId): WorkspaceTransfer {
            $leaseEndsAt = now()->addMinutes((int) config('file-storage.workspaceTransfer.leaseMinutes', 15));
            $transfer = WorkspaceTransfer::query()->create([
                'publicId' => (string) Str::ulid(),
                'idRequestingUser' => $principal->id,
                'sourceScope' => $source->scope->value,
                'sourceUserId' => $source->scope->value === 'PERSONAL' ? $source->ownerId : null,
                'sourceTenantId' => $source->tenantId,
                'destinationScope' => $destination->scope->value,
                'destinationUserId' => $destination->scope->value === 'PERSONAL' ? $destination->ownerId : null,
                'destinationTenantId' => $destination->tenantId,
                'destinationFolderId' => $destinationFolderId,
                'mode' => $mode,
                'selectionManifest' => $items,
                'state' => WorkspaceTransferState::Pending,
                'totalItems' => count($items),
                'idempotencyKey' => $idempotencyKey,
                'correlationId' => $correlationId,
                'leaseExpiresAt' => $leaseEndsAt,
            ]);
            $leases = array_map(fn (?int $rootFolderId): array => [
                'target' => $source, 'rootFolderId' => $rootFolderId, 'side' => WorkspaceTransferReservationSide::Source,
            ], $this->sourceRoots($source, $items));
            $leases[] = ['target' => $destination, 'rootFolderId' => $destinationFolderId, 'side' => WorkspaceTransferReservationSide::Destination];
            $this->reservations->acquireAll($transfer, $leases);

            return $transfer->refresh();
        });
        ProcessWorkspaceTransfer::dispatch($transfer->publicId)->afterCommit();

        return $transfer;
    }

    /** @param list<array{type: string, id: int}> $items @return list<?int> */
    private function sourceRoots(DriveWorkspaceTarget $target, array $items): array
    {
        $roots = [];
        foreach ($items as $item) {
            if ($item['type'] === 'folder') {
                $roots[] = $item['id'];

                continue;
            }
            $query = StoredFilePossession::query()->whereKey($item['id'])->where('storageArea', 'WORKSPACE')->where('state', 'ACTIVE');
            $target->scope->value === 'TENANT' ? $query->where('idTenant', $target->ownerId) : $query->whereNotNull('idUser')->whereNull('idTenant');
            $roots[] = $query->value('idWorkspaceFolder');
        }
        if (in_array(null, $roots, true)) {
            return [null];
        }

        return array_values(array_unique($roots));
    }
}
