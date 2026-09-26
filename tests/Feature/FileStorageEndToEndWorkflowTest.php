<?php

namespace Tests\Feature;

use App\Contracts\FileStorage\V1\FilePossessionOperationRequest;
use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\StoredManagedVersion;
use App\Contracts\FileStorage\V1\StoreManagedVersionRequest;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageEndToEndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rinosone-file-e2e-'.str()->uuid();
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

    public function test_deduplication_branching_binding_trash_and_private_read_work_together(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $initialContents = str_repeat('shared private document ', 24);
        $first = $this->storeManaged($firstOwner, $initialContents, 'first.txt');
        $second = $this->storeManaged($secondOwner, $initialContents, 'second.txt');

        $this->assertSame($first->contentId, $second->contentId);
        $this->assertSame($first->storageObjectId, $second->storageObjectId);

        $secondReplacement = $this->storeManaged($secondOwner, 'second owner branch', 'second-branch.txt');
        $this->assertSame($second->versionId, StoredFileVersion::query()->findOrFail($secondReplacement->versionId)->idParentFileVersion);

        $workspacePossession = $this->createWorkspacePossession($firstOwner, $first, strlen($initialContents));
        $operation = new FilePossessionOperationRequest(FileStorageOwnerType::User, $firstOwner->id, $workspacePossession->id);

        app(FileStorageV1::class)->trashPossession($operation);
        $this->assertDatabaseHas('file_filePossession', ['id' => $workspacePossession->id, 'state' => 'TRASHED']);
        $this->assertSame(strlen($initialContents), StoredFileOwnerUsage::query()->where('idUser', $firstOwner->id)->value('trashBytes'));

        app(FileStorageV1::class)->restorePossession($operation);
        app(FileStorageV1::class)->releasePossession($operation);
        $this->assertSame('RELEASED', $workspacePossession->fresh()->state);

        $read = app(FileStorageV1::class)->authorizePrivateRead(new FilePrivateReadRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $firstOwner->id,
            bindingKey: 'USER_PROFILE_AVATAR',
        ));
        $stream = $read->openStream();

        $this->assertSame($initialContents, stream_get_contents($stream));
        fclose($stream);
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

    private function createWorkspacePossession(User $user, StoredManagedVersion $stored, int $logicalSizeBytes): StoredFilePossession
    {
        $version = StoredFileVersion::query()->findOrFail($stored->versionId);
        $possession = StoredFilePossession::query()->create([
            'idFile' => $stored->fileId,
            'idCurrentFileVersion' => $stored->versionId,
            'idUser' => $user->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'workspace-copy.txt',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => $logicalSizeBytes,
        ]);
        $usage = StoredFileOwnerUsage::query()->where('idUser', $user->id)->firstOrFail();
        $usage->forceFill([
            'workspaceBytes' => $logicalSizeBytes,
            'totalBytes' => (int) $usage->totalBytes + $logicalSizeBytes,
        ])->save();

        $this->assertSame($stored->fileId, $version->idFile);

        return $possession;
    }
}
