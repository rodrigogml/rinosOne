<?php

namespace App\Jobs\FileStorage;

use App\Services\FileStorage\Drive\DriveWorkspaceExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeExpiredWorkspaceExports implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(DriveWorkspaceExportService $exports): void
    {
        $exports->purgeExpired();
    }
}
