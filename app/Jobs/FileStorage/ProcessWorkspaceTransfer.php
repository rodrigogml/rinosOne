<?php

namespace App\Jobs\FileStorage;

use App\Models\FileStorage\WorkspaceTransfer;
use App\Services\FileStorage\Drive\WorkspaceTransferExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Processes one opaque logical Drive transfer after it has atomically acquired its reservations. */
class ProcessWorkspaceTransfer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $transferId) {}

    public function handle(WorkspaceTransferExecutionService $executor): void
    {
        $transfer = WorkspaceTransfer::query()->with('requestingUser')->where('publicId', $this->transferId)->first();
        if ($transfer === null || $transfer->state->isTerminal() || $transfer->requestingUser === null) {
            return;
        }
        if ($transfer->attemptCount >= (int) config('file-storage.workspaceTransfer.maximumAttempts', 3)) {
            return;
        }
        $transfer->increment('attemptCount');
        $executor->execute($transfer->requestingUser, $transfer->refresh());
    }
}
