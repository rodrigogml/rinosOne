<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Exception\FileStorageIngestionException;
use App\Services\FileStorage\FileStorageContentIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageContentIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rinosone-file-storage-'.str()->uuid();
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_it_stages_and_promotes_content_to_a_private_hash_fragmented_path(): void
    {
        $source = $this->sourceFile('statement.txt', 'private statement');

        $result = app(FileStorageContentIngestionService::class)->ingest($source);

        $expectedHash = hash('sha256', 'private statement');
        $expectedKey = 'objects/sha256/'.substr($expectedHash, 0, 2).'/'.substr($expectedHash, 2, 2).'/'.$expectedHash.'.blob';

        $this->assertSame($expectedHash, $result->logicalSha256);
        $this->assertSame($expectedKey, $result->storageKey);
        $this->assertFalse($result->reusedStorageObject);
        $this->assertSame('private statement', Storage::disk('file-private')->get($expectedKey));
        $this->assertSame([], Storage::disk('file-private')->allFiles('.file-storage-staging'));
        $this->assertDatabaseHas('file_fileContent', ['id' => $result->contentId, 'logicalSha256' => $expectedHash]);
        $this->assertDatabaseHas('file_storageObject', ['id' => $result->storageObjectId, 'state' => 'ACTIVE']);
    }

    public function test_it_reuses_the_same_content_and_object_for_identical_bytes(): void
    {
        $first = app(FileStorageContentIngestionService::class)->ingest($this->sourceFile('one.txt', 'same bytes'));
        $second = app(FileStorageContentIngestionService::class)->ingest($this->sourceFile('two.txt', 'same bytes'));

        $this->assertSame($first->contentId, $second->contentId);
        $this->assertSame($first->storageObjectId, $second->storageObjectId);
        $this->assertTrue($second->reusedStorageObject);
        $this->assertDatabaseCount('file_fileContent', 1);
        $this->assertDatabaseCount('file_storageObject', 1);
    }

    public function test_it_adopts_a_matching_orphaned_physical_object_without_overwriting_it(): void
    {
        $contents = 'recoverable bytes';
        $hash = hash('sha256', $contents);
        $storageKey = 'objects/sha256/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash.'.blob';
        Storage::disk('file-private')->put($storageKey, $contents);

        $result = app(FileStorageContentIngestionService::class)->ingest($this->sourceFile('recover.txt', $contents));

        $this->assertSame($storageKey, $result->storageKey);
        $this->assertFalse($result->reusedStorageObject);
        $this->assertSame($contents, Storage::disk('file-private')->get($storageKey));
        $this->assertDatabaseCount('file_storageObject', 1);
    }

    public function test_it_rejects_an_unavailable_source_without_creating_catalog_records(): void
    {
        $this->expectException(FileStorageIngestionException::class);
        $this->expectExceptionMessage('source is unavailable');

        try {
            app(FileStorageContentIngestionService::class)->ingest($this->temporaryDirectory.DIRECTORY_SEPARATOR.'missing.txt');
        } finally {
            $this->assertDatabaseCount('file_fileContent', 0);
            $this->assertDatabaseCount('file_storageObject', 0);
        }
    }

    private function sourceFile(string $name, string $contents): string
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $contents);

        return $path;
    }
}
