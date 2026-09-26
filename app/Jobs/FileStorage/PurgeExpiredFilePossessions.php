<?php

namespace App\Jobs\FileStorage;

use App\Services\FileStorage\FileStoragePossessionLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeExpiredFilePossessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Releases a bounded batch of workspace possessions that have passed their private trash deadline.
     */
    public function handle(FileStoragePossessionLifecycleService $lifecycleService): void
    {
        $lifecycleService->purgeExpiredTrash();
    }
}
