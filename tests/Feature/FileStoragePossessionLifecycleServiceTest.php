<?php

namespace Tests\Feature;

use App\Contracts\FileStorage\V1\FilePossessionOperationRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\StoreManagedVersionRequest;
use App\Domain\FileStorage\Exception\FileStorageVersionException;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileStorageBackend;
use App\Models\FileStorage\StoredFileStorageObject;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\User;
use App\Services\FileStorage\FileStoragePossessionLifecycleService;
use App\Services\FileStorage\FileStorageRetentionPurgeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStoragePossessionLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
        Carbon::setTestNow('2026-01-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_trashes_and_restores_a_workspace_possession_without_changing_total_quota(): void
    {
        [$user, $possession] = $this->createWorkspacePossession('quarterly-report');
        $request = $this->operationFor($user->id, $possession->id);

        $trashed = app(FileStorageV1::class)->trashPossession($request);
        $usage = StoredFileOwnerUsage::query()->where('idUser', $user->id)->firstOrFail();

        $this->assertSame('TRASHED', $trashed->state);
        $this->assertSame('2026-01-31 12:00:00', $trashed->purgeAfter?->format('Y-m-d H:i:s'));
        $this->assertSame(0, $usage->workspaceBytes);
        $this->assertSame(strlen('quarterly-report'), $usage->trashBytes);
        $this->assertSame(strlen('quarterly-report'), $usage->totalBytes);

        $restored = app(FileStorageV1::class)->restorePossession($request);
        $usage->refresh();

        $this->assertSame('ACTIVE', $restored->state);
        $this->assertNull($restored->purgeAfter);
        $this->assertSame(strlen('quarterly-report'), $usage->workspaceBytes);
        $this->assertSame(0, $usage->trashBytes);
        $this->assertSame(strlen('quarterly-report'), $usage->totalBytes);
    }

    public function test_it_releases_only_the_requesting_owners_trashed_possession_and_retains_shared_content(): void
    {
        [$firstUser, $firstPossession, $content, $object] = $this->createWorkspacePossession('shared-content');
        [$secondUser, $secondPossession] = $this->createWorkspacePossession('shared-content', $content);

        app(FileStorageV1::class)->trashPossession($this->operationFor($firstUser->id, $firstPossession->id));
        $released = app(FileStorageV1::class)->releasePossession($this->operationFor($firstUser->id, $firstPossession->id));

        $this->assertSame('RELEASED', $released->state);
        $this->assertNotNull($released->releasedAt);
        $this->assertSame(0, StoredFileOwnerUsage::query()->where('idUser', $firstUser->id)->value('totalBytes'));
        $this->assertDatabaseHas('file_filePossession', ['id' => $secondPossession->id, 'state' => 'ACTIVE']);
        $this->assertSame(strlen('shared-content'), StoredFileOwnerUsage::query()->where('idUser', $secondUser->id)->value('totalBytes'));
        $this->assertSame('2026-03-02 12:00:00', $object->fresh()->retentionUntil?->format('Y-m-d H:i:s'));
    }

    public function test_it_refuses_generic_operations_for_system_managed_possessions(): void
    {
        $user = User::factory()->create();
        $source = tempnam(sys_get_temp_dir(), 'rinosone-file-lifecycle-');
        file_put_contents($source, 'managed file');
        $managed = app(FileStorageV1::class)->storeManagedVersion(new StoreManagedVersionRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $user->id,
            sourcePath: $source,
            purpose: 'USER_PROFILE_AVATAR',
            displayName: 'avatar.png',
            bindingKey: 'USER_PROFILE_AVATAR',
        ));
        unlink($source);

        $this->expectException(FileStorageVersionException::class);
        $this->expectExceptionMessage('not allowed for system-managed');

        app(FileStorageV1::class)->trashPossession($this->operationFor($user->id, $managed->possessionId));
    }

    public function test_expired_trash_is_released_and_unreferenced_bytes_are_purged_after_technical_retention(): void
    {
        config([
            'file-storage.retention.backupDays' => 1,
            'file-storage.retention.technicalDays' => 1,
        ]);
        [$user, $possession, $content, $object] = $this->createWorkspacePossession('expired-content');
        Storage::disk('file-private')->put($object->storageKey, 'expired-content');

        app(FileStorageV1::class)->trashPossession($this->operationFor($user->id, $possession->id));
        Carbon::setTestNow('2026-02-01 12:00:01');

        $this->assertSame(1, app(FileStoragePossessionLifecycleService::class)->purgeExpiredTrash());
        $this->assertSame('RELEASED', $possession->fresh()->state);
        $this->assertSame(0, StoredFileOwnerUsage::query()->where('idUser', $user->id)->value('totalBytes'));

        Carbon::setTestNow('2026-02-03 12:00:01');

        $purgeService = app(FileStorageRetentionPurgeService::class);
        $this->assertSame(1, $purgeService->purgeEligibleVersions());
        $this->assertSame(1, $purgeService->purgeEligibleStorageObjects());
        $this->assertDatabaseMissing('file_fileVersion', ['id' => $possession->idCurrentFileVersion]);
        $this->assertDatabaseMissing('file_storageObject', ['id' => $object->id]);
        $this->assertDatabaseMissing('file_fileContent', ['id' => $content->id]);
        Storage::disk('file-private')->assertMissing($object->storageKey);
    }

    /**
     * @return array{0: User, 1: StoredFilePossession, 2: StoredFileContent, 3: StoredFileStorageObject}
     */
    private function createWorkspacePossession(string $bytes, ?StoredFileContent $existingContent = null): array
    {
        $user = User::factory()->create();
        $content = $existingContent ?? StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', $bytes),
            'logicalSizeBytes' => strlen($bytes),
            'detectedMimeType' => 'text/plain',
            'declaredExtension' => 'txt',
        ]);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create([
            'idFile' => $file->id,
            'idFileContent' => $content->id,
            'versionNumber' => 1,
        ]);
        $possession = StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $user->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'report.txt',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => strlen($bytes),
        ]);
        StoredFileOwnerUsage::query()->create([
            'idUser' => $user->id,
            'workspaceBytes' => strlen($bytes),
            'systemManagedBytes' => 0,
            'trashBytes' => 0,
            'totalBytes' => strlen($bytes),
        ]);
        $backend = StoredFileStorageBackend::query()->firstOrCreate([
            'backendKey' => 'local-private',
        ], ['state' => 'ACTIVE']);
        $object = StoredFileStorageObject::query()->firstOrCreate([
            'idFileContent' => $content->id,
            'idStorageBackend' => $backend->id,
            'storedSha256' => $content->logicalSha256,
            'encoding' => 'IDENTITY',
        ], [
            'storageKey' => 'objects/test/'.$content->logicalSha256.'.blob',
            'storedSizeBytes' => strlen($bytes),
            'state' => 'ACTIVE',
        ]);

        return [$user, $possession, $content, $object];
    }

    private function operationFor(int $userId, int $possessionId): FilePossessionOperationRequest
    {
        return new FilePossessionOperationRequest(FileStorageOwnerType::User, $userId, $possessionId);
    }
}
