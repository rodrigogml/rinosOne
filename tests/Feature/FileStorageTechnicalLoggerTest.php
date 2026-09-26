<?php

namespace Tests\Feature;

use App\Services\FileStorage\FileStorageTechnicalLogger;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class FileStorageTechnicalLoggerTest extends TestCase
{
    public function test_it_uses_a_dedicated_channel_and_filters_sensitive_context(): void
    {
        $logger = \Mockery::mock(Logger::class);

        Log::shouldReceive('channel')
            ->once()
            ->with('file-storage')
            ->andReturn($logger);

        $logger->shouldReceive('warning')
            ->once()
            ->with('file-storage.failure', [
                'event' => 'ingestion.failed',
                'exception' => RuntimeException::class,
                'backendKey' => 'local-private',
                'operation' => 'ingestion',
            ]);

        app(FileStorageTechnicalLogger::class)->failure(
            'ingestion.failed',
            new RuntimeException('C:\\private\\secret-name.txt'),
            [
                'backendKey' => 'local-private',
                'operation' => 'ingestion',
                'storageKey' => 'objects/sha256/private-hash.blob',
                'displayName' => 'secret-name.txt',
                'logicalSha256' => 'sensitive-hash',
            ],
        );
    }

    public function test_the_file_storage_channel_uses_thirty_day_retention_by_default(): void
    {
        $this->assertSame(30, config('logging.channels.file-storage.days'));
    }
}
