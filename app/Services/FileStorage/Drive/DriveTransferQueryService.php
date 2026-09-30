<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Models\FileStorage\WorkspaceTransfer;
use App\Models\User;

/** Projects or cancels only the requester's own opaque Drive transfer. */
class DriveTransferQueryService
{
    public function __construct(private readonly WorkspaceTransferLifecycleService $lifecycle) {}

    public function status(User $principal, string $publicId): WorkspaceTransfer
    {
        $transfer = WorkspaceTransfer::query()->where('publicId', $publicId)->where('idRequestingUser', $principal->id)->first();
        if ($transfer === null) {
            throw new DriveWorkspaceCommandException('DRIVE_LOCATION_NOT_FOUND');
        }

        return $transfer;
    }

    public function cancel(User $principal, string $publicId): WorkspaceTransfer
    {
        $transfer = $this->status($principal, $publicId);
        if ($transfer->state->value !== 'PENDING') {
            throw new DriveWorkspaceCommandException('DRIVE_TRANSFER_INVALID');
        }

        return $this->lifecycle->cancel($transfer);
    }

    /** @return array<string, mixed> */
    public function response(WorkspaceTransfer $transfer): array
    {
        return [
            'transferId' => $transfer->publicId,
            'state' => $transfer->state->value,
            'mode' => $transfer->mode->value,
            'totalItems' => $transfer->totalItems,
            'processedItems' => $transfer->processedItems,
            'destinationTarget' => ['kind' => $transfer->destinationScope === 'PERSONAL' ? 'personal' : 'tenant', 'tenantId' => $transfer->destinationTenantId],
            'failureCode' => $transfer->failureCode,
        ];
    }
}
