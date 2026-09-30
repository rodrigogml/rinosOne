<?php

namespace App\Jobs\FileStorage;

use App\Services\FileStorage\Drive\DriveWorkspaceExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWorkspaceExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $exportId) {}

    /** Generates only the private archive represented by the opaque export identifier. */
    public function handle(DriveWorkspaceExportService $exports): void
    {
        $exports->generate($this->exportId);
    }
}
