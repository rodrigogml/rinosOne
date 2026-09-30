<?php

namespace App\Jobs\FileStorage;

use App\Services\FileStorage\Drive\WorkspaceTransferRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Schedules recovery of transfers whose worker stopped renewing its lease. */
class RecoverExpiredWorkspaceTransfers implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(WorkspaceTransferRecoveryService $recovery): void
    {
        $recovery->recoverExpired();
    }
}
