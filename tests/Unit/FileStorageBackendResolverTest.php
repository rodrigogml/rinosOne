<?php

namespace Tests\Unit;

use App\Infrastructure\FileStorage\FileStorageBackendResolver;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class FileStorageBackendResolverTest extends TestCase
{
    public function test_it_resolves_the_default_private_disk_and_removes_its_write_probe(): void
    {
        Storage::fake('file-private');

        $resolver = app(FileStorageBackendResolver::class);

        $resolver->ensureWritable();

        $this->assertSame('file-private', config('file-storage.backends.local-private.disk'));
        $this->assertSame([], Storage::disk('file-private')->allFiles('.file-storage-health'));
    }

    public function test_it_refuses_a_backend_that_is_not_active_for_writing(): void
    {
        Storage::fake('file-private');
        config(['file-storage.backends.local-private.state' => 'READ_ONLY']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('not available for writing');

        app(FileStorageBackendResolver::class)->ensureWritable();
    }
}
