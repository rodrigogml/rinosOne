<?php

namespace App\Jobs\FileStorage;

use App\Services\FileStorage\FileStorageCompressionReprocessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReprocessFileStorageCompression implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Applies the configured representation policy to a bounded content batch without changing logical content.
     */
    public function handle(FileStorageCompressionReprocessor $compressionReprocessor): void
    {
        $compressionReprocessor->reprocess();
    }
}
