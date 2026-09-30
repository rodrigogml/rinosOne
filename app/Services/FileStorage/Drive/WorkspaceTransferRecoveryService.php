<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\WorkspaceTransferState;
use App\Jobs\FileStorage\ProcessWorkspaceTransfer;
use App\Models\FileStorage\WorkspaceTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Recovers expired logical transfers without leaving branch reservations permanently active. */
class WorkspaceTransferRecoveryService
{
    public function recoverExpired(?Carbon $now = null): int
    {
        $current = $now ?? now();
        $ids = WorkspaceTransfer::query()
            ->whereIn('state', [WorkspaceTransferState::Pending->value, WorkspaceTransferState::Processing->value])
            ->whereNotNull('leaseExpiresAt')
            ->where('leaseExpiresAt', '<=', $current)
            ->pluck('id');
        $recovered = 0;
        foreach ($ids as $id) {
            $result = DB::transaction(function () use ($id, $current): ?string {
                $transfer = WorkspaceTransfer::query()->with('reservations')->lockForUpdate()->find($id);
                if ($transfer === null || $transfer->state->isTerminal() || $transfer->leaseExpiresAt === null || $transfer->leaseExpiresAt->isAfter($current)) {
                    return null;
                }
                if ($transfer->attemptCount < (int) config('file-storage.workspaceTransfer.maximumAttempts', 3)) {
                    $lease = $current->copy()->addMinutes((int) config('file-storage.workspaceTransfer.leaseMinutes', 15));
                    $transfer->forceFill(['state' => WorkspaceTransferState::Pending, 'heartbeatAt' => null, 'leaseExpiresAt' => $lease])->save();
                    $transfer->reservations()->update(['leaseExpiresAt' => $lease]);

                    return $transfer->publicId;
                }
                $transfer->forceFill(['state' => WorkspaceTransferState::Failed, 'failureCode' => 'DRIVE_TRANSFER_STALE', 'completedAt' => $current, 'leaseExpiresAt' => null])->save();
                $transfer->reservations()->delete();

                return '';
            });
            if ($result === null) {
                continue;
            }
            $recovered++;
            if ($result !== '') {
                ProcessWorkspaceTransfer::dispatch($result)->afterCommit();
            }
        }

        return $recovered;
    }
}
