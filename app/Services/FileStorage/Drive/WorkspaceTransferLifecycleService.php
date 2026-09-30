<?php

namespace App\Services\FileStorage\Drive;

use App\Domain\FileStorage\Drive\WorkspaceTransferState;
use App\Models\FileStorage\WorkspaceTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Owns safe transfer state transitions and the idempotent release of their branch reservations. */
class WorkspaceTransferLifecycleService
{
    public function begin(WorkspaceTransfer $transfer, ?Carbon $now = null): WorkspaceTransfer
    {
        return $this->transition($transfer, WorkspaceTransferState::Pending, WorkspaceTransferState::Processing, function (WorkspaceTransfer $locked) use ($now): void {
            $current = $now ?? now();
            $locked->forceFill(['startedAt' => $locked->startedAt ?? $current, 'heartbeatAt' => $current, 'leaseExpiresAt' => $this->leaseEndsAt($current)])->save();
            $locked->reservations()->update(['leaseExpiresAt' => $locked->leaseExpiresAt]);
        });
    }

    public function renewLease(WorkspaceTransfer $transfer, ?Carbon $now = null): WorkspaceTransfer
    {
        return DB::transaction(function () use ($transfer, $now): WorkspaceTransfer {
            $locked = WorkspaceTransfer::query()->with('reservations')->lockForUpdate()->findOrFail($transfer->id);
            if ($locked->state !== WorkspaceTransferState::Processing) {
                throw new LogicException('Only a processing workspace transfer can renew its lease.');
            }
            $current = $now ?? now();
            $locked->forceFill(['heartbeatAt' => $current, 'leaseExpiresAt' => $this->leaseEndsAt($current)])->save();
            $locked->reservations()->update(['leaseExpiresAt' => $locked->leaseExpiresAt]);

            return $locked->refresh();
        });
    }

    public function complete(WorkspaceTransfer $transfer, ?Carbon $now = null): WorkspaceTransfer
    {
        return $this->finish($transfer, WorkspaceTransferState::Completed, null, $now);
    }

    public function fail(WorkspaceTransfer $transfer, string $failureCode, ?Carbon $now = null): WorkspaceTransfer
    {
        return $this->finish($transfer, WorkspaceTransferState::Failed, $failureCode, $now);
    }

    public function cancel(WorkspaceTransfer $transfer, ?Carbon $now = null): WorkspaceTransfer
    {
        return $this->transition($transfer, WorkspaceTransferState::Pending, WorkspaceTransferState::Cancelled, function (WorkspaceTransfer $locked) use ($now): void {
            $locked->forceFill(['completedAt' => $now ?? now(), 'leaseExpiresAt' => null])->save();
            $locked->reservations()->delete();
        });
    }

    /** Fails expired active transfers and releases every corresponding reservation exactly once. */
    public function failExpired(?Carbon $now = null): int
    {
        $current = $now ?? now();
        $ids = WorkspaceTransfer::query()
            ->whereIn('state', [WorkspaceTransferState::Pending->value, WorkspaceTransferState::Processing->value])
            ->whereNotNull('leaseExpiresAt')
            ->where('leaseExpiresAt', '<=', $current)
            ->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $current): void {
                $transfer = WorkspaceTransfer::query()->with('reservations')->lockForUpdate()->find($id);
                if ($transfer === null || $transfer->state->isTerminal() || $transfer->leaseExpiresAt === null || $transfer->leaseExpiresAt->isAfter($current)) {
                    return;
                }
                $transfer->forceFill(['state' => WorkspaceTransferState::Failed, 'failureCode' => 'DRIVE_TRANSFER_STALE', 'completedAt' => $current, 'leaseExpiresAt' => null])->save();
                $transfer->reservations()->delete();
            });
        }

        return $ids->count();
    }

    private function finish(WorkspaceTransfer $transfer, WorkspaceTransferState $state, ?string $failureCode, ?Carbon $now): WorkspaceTransfer
    {
        return DB::transaction(function () use ($transfer, $state, $failureCode, $now): WorkspaceTransfer {
            $locked = WorkspaceTransfer::query()->with('reservations')->lockForUpdate()->findOrFail($transfer->id);
            if ($locked->state->isTerminal()) {
                return $locked;
            }
            $locked->forceFill(['state' => $state, 'failureCode' => $failureCode, 'completedAt' => $now ?? now(), 'leaseExpiresAt' => null])->save();
            $locked->reservations()->delete();

            return $locked->refresh();
        });
    }

    /** @param callable(WorkspaceTransfer): void $apply */
    private function transition(WorkspaceTransfer $transfer, WorkspaceTransferState $from, WorkspaceTransferState $to, callable $apply): WorkspaceTransfer
    {
        return DB::transaction(function () use ($transfer, $from, $to, $apply): WorkspaceTransfer {
            $locked = WorkspaceTransfer::query()->with('reservations')->lockForUpdate()->findOrFail($transfer->id);
            if ($locked->state !== $from) {
                throw new LogicException('The workspace transfer state does not allow this transition.');
            }
            $locked->state = $to;
            $apply($locked);

            return $locked->refresh();
        });
    }

    private function leaseEndsAt(Carbon $now): Carbon
    {
        $minutes = (int) config('file-storage.workspaceTransfer.leaseMinutes', 15);
        if ($minutes < 1) {
            throw new LogicException('The workspace transfer lease duration must be positive.');
        }

        return $now->copy()->addMinutes($minutes);
    }
}
