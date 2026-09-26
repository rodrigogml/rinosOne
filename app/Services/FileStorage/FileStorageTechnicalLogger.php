<?php

namespace App\Services\FileStorage;

use Illuminate\Support\Facades\Log;
use Throwable;

class FileStorageTechnicalLogger
{
    /**
     * Records a technical failure with a deliberately minimal context that cannot reveal file content or location.
     *
     * @param  array<string, int|string>  $context
     */
    public function failure(string $event, Throwable $exception, array $context = []): void
    {
        $allowedContext = array_intersect_key($context, array_flip(['backendKey', 'storageObjectId', 'contentId', 'operation']));

        Log::channel('file-storage')->warning('file-storage.failure', [
            'event' => $event,
            'exception' => $exception::class,
            ...$allowedContext,
        ]);
    }
}
