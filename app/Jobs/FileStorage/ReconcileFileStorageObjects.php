<?php

namespace App\Jobs\FileStorage;

use App\Services\FileStorage\FileStorageReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileFileStorageObjects implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Reconciles a bounded private catalog with configured storage backends without exposing file paths.
     */
    public function handle(FileStorageReconciliationService $reconciliationService): void
    {
        $reconciliationService->reconcile();
    }
}
