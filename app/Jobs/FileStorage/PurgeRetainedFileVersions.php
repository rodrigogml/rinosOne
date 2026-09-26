<?php

namespace App\Jobs\FileStorage;

use App\Services\FileStorage\FileStorageRetentionPurgeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeRetainedFileVersions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Removes bounded batches of unreferenced version leaves after technical retention has elapsed.
     */
    public function handle(FileStorageRetentionPurgeService $retentionPurgeService): void
    {
        $retentionPurgeService->purgeEligibleVersions();
    }
}
