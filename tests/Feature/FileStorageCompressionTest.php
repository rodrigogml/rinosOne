<?php

namespace Tests\Feature;

use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\StoredManagedVersion;
use App\Contracts\FileStorage\V1\StoreManagedVersionRequest;
use App\Models\FileStorage\StoredFileStorageObject;
use App\Models\User;
use App\Services\FileStorage\FileStorageCompressionReprocessor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageCompressionTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rinosone-file-compression-'.str()->uuid();
        mkdir($this->temporaryDirectory, 0700, true);
        Carbon::setTestNow('2026-01-10 12:00:00');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->temporaryDirectory);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_promotes_gzip_only_when_a_matching_rule_reduces_the_stored_bytes(): void
    {
        config(['file-storage.compression.rules' => [$this->gzipRule('txt')]]);
        $user = User::factory()->create();
        $contents = str_repeat('compressible file storage content ', 80);
        $stored = $this->storeManaged($user, $contents, 'compressible.txt');
        $object = StoredFileStorageObject::query()->findOrFail($stored->storageObjectId);

        $this->assertSame('GZIP', $object->encoding);
        $this->assertLessThan(strlen($contents), $object->storedSizeBytes);
        $this->assertNotSame(hash('sha256', $contents), $object->storedSha256);

        $read = app(FileStorageV1::class)->authorizePrivateRead(new FilePrivateReadRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $user->id,
            bindingKey: 'USER_PROFILE_AVATAR',
        ));
        $stream = $read->openStream();

        $this->assertSame($contents, stream_get_contents($stream));
        fclose($stream);
    }

    public function test_it_keeps_identity_when_gzip_has_no_size_gain(): void
    {
        config(['file-storage.compression.rules' => [$this->gzipRule('bin')]]);
        $user = User::factory()->create();
        $contents = random_bytes(1024);
        $stored = $this->storeManaged($user, $contents, 'random.bin');
        $object = StoredFileStorageObject::query()->findOrFail($stored->storageObjectId);

        $this->assertSame('IDENTITY', $object->encoding);
        $this->assertSame(strlen($contents), $object->storedSizeBytes);
        $this->assertSame(hash('sha256', $contents), $object->storedSha256);
    }

    public function test_reprocessing_switches_representations_idempotently_and_retains_the_replaced_object(): void
    {
        config(['file-storage.compression.rules' => []]);
        $user = User::factory()->create();
        $contents = str_repeat('reprocess this content safely ', 100);
        $stored = $this->storeManaged($user, $contents, 'reprocess.txt');
        $identity = StoredFileStorageObject::query()->findOrFail($stored->storageObjectId);

        $this->assertSame('IDENTITY', $identity->encoding);
        config(['file-storage.compression.rules' => [$this->gzipRule('txt')]]);

        $reprocessor = app(FileStorageCompressionReprocessor::class);
        $this->assertSame(1, $reprocessor->reprocess());

        $gzip = StoredFileStorageObject::query()->where('idFileContent', $identity->idFileContent)->where('state', 'ACTIVE')->firstOrFail();
        $identity->refresh();
        $this->assertSame('GZIP', $gzip->encoding);
        $this->assertSame('RETIRED', $identity->state);
        $this->assertNotNull($identity->retentionUntil);
        $this->assertSame(0, $reprocessor->reprocess());

        config(['file-storage.compression.rules' => []]);
        $this->assertSame(1, $reprocessor->reprocess());
        $identity->refresh();
        $gzip->refresh();

        $this->assertSame('ACTIVE', $identity->state);
        $this->assertNull($identity->retentionUntil);
        $this->assertSame('RETIRED', $gzip->state);
        $this->assertNotNull($gzip->retentionUntil);
    }

    /** @return array{mimeTypes: array<int, string>, extensions: array<int, string>, encoding: string} */
    private function gzipRule(string $extension): array
    {
        return [
            'mimeTypes' => [],
            'extensions' => [$extension],
            'encoding' => 'GZIP',
        ];
    }

    private function storeManaged(User $user, string $contents, string $name): StoredManagedVersion
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $contents);

        return app(FileStorageV1::class)->storeManagedVersion(new StoreManagedVersionRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $user->id,
            sourcePath: $path,
            purpose: 'USER_PROFILE_AVATAR',
            displayName: $name,
            bindingKey: 'USER_PROFILE_AVATAR',
        ));
    }
}
