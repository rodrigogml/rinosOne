<?php

namespace Tests\Feature;

use App\Contracts\FileStorage\V1\FilePrivateReadRequest;
use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Contracts\FileStorage\V1\ReserveFileVersionDerivativeRequest;
use App\Contracts\FileStorage\V1\StoredManagedVersion;
use App\Contracts\FileStorage\V1\StoreFileVersionMetadataRequest;
use App\Contracts\FileStorage\V1\StoreManagedVersionRequest;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Exception\FileStorageAccessException;
use App\Domain\FileStorage\Exception\FileStorageDerivativeException;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileStorageBackend;
use App\Models\FileStorage\StoredFileStorageObject;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\StoredFileVersionDerivative;
use App\Models\FileStorage\StoredFileVersionMetadata;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use App\Services\FileStorage\FileStorageReconciliationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStoragePrivateOperationsTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rinosone-file-private-'.str()->uuid();
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

    public function test_private_read_returns_an_authorized_stream_without_exposing_the_storage_path(): void
    {
        $owner = User::factory()->create();
        $stored = $this->storeManaged($owner, 'private-avatar');

        $read = app(FileStorageV1::class)->authorizePrivateRead(new FilePrivateReadRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $owner->id,
            bindingKey: 'USER_PROFILE_AVATAR',
        ));
        $stream = $read->openStream();

        $this->assertSame('private-avatar', stream_get_contents($stream));
        $this->assertSame('text/plain', $read->detectedMimeType);
        $this->assertObjectNotHasProperty('storageKey', $read);
        fclose($stream);

        $otherOwner = User::factory()->create();
        $this->expectException(FileStorageAccessException::class);
        $this->expectExceptionMessage('unavailable');

        app(FileStorageV1::class)->authorizePrivateRead(new FilePrivateReadRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $otherOwner->id,
            possessionId: $stored->possessionId,
        ));
    }

    public function test_it_persists_version_metadata_only_for_an_authorized_possession(): void
    {
        $owner = User::factory()->create();
        $stored = $this->storeManaged($owner, 'metadata-content');

        app(FileStorageV1::class)->storeVersionMetadata(new StoreFileVersionMetadataRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $owner->id,
            possessionId: $stored->possessionId,
            metadataKey: 'capture',
            metadataValue: ['takenAt' => '2026-01-01T10:00:00Z', 'orientation' => 1],
            source: 'EXTRACTED',
        ));

        $metadata = StoredFileVersionMetadata::query()->firstOrFail();
        $this->assertSame($stored->versionId, $metadata->idFileVersion);
        $this->assertSame(['takenAt' => '2026-01-01T10:00:00Z', 'orientation' => 1], $metadata->metadataValue);
        $this->assertSame('EXTRACTED', $metadata->source);
    }

    public function test_a_collaborator_can_read_a_workspace_file_through_an_inherited_folder_relation(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $content = $this->content('shared-workspace-file');
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);
        $possession = StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $owner->id,
            'idWorkspaceFolder' => $folder->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'shared.txt',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => $content->logicalSizeBytes,
        ]);
        $backend = StoredFileStorageBackend::query()->create(['backendKey' => 'local-private', 'state' => 'ACTIVE']);
        $object = StoredFileStorageObject::query()->create([
            'idFileContent' => $content->id,
            'idStorageBackend' => $backend->id,
            'storedSha256' => $content->logicalSha256,
            'storageKey' => 'objects/test/shared-workspace-file.blob',
            'encoding' => 'IDENTITY',
            'storedSizeBytes' => $content->logicalSizeBytes,
            'state' => 'ACTIVE',
        ]);
        Storage::disk('file-private')->put($object->storageKey, 'shared-workspace-file');
        app(AuthorizationResourceRelationService::class)->create(
            new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal),
            'READ',
            $collaborator,
        );

        $read = app(FileStorageV1::class)->authorizePrivateRead(new FilePrivateReadRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $owner->id,
            possessionId: $possession->id,
            principalUserId: $collaborator->id,
        ));
        $stream = $read->openStream();

        $this->assertSame('shared-workspace-file', stream_get_contents($stream));
        fclose($stream);
    }

    public function test_a_collaborator_requires_edit_relation_to_write_workspace_file_metadata_and_loses_it_when_revoked(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $content = $this->content('shared-edit-file');
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);
        $possession = StoredFilePossession::query()->create(['idFile' => $file->id, 'idCurrentFileVersion' => $version->id, 'idUser' => $owner->id, 'idWorkspaceFolder' => $folder->id, 'storageArea' => 'WORKSPACE', 'displayName' => 'editable.txt', 'state' => 'ACTIVE', 'logicalSizeBytes' => $content->logicalSizeBytes]);
        $relations = app(AuthorizationResourceRelationService::class);
        $resource = new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal);
        $edit = $relations->create($resource, 'EDIT', $collaborator);

        app(FileStorageV1::class)->storeVersionMetadata(new StoreFileVersionMetadataRequest(
            FileStorageOwnerType::User, $owner->id, $possession->id, 'collaborative-edit', ['approved' => true], 'DECLARED', $collaborator->id,
        ));
        $this->assertDatabaseHas('file_versionMetadata', ['idFileVersion' => $version->id, 'metadataKey' => 'collaborative-edit']);

        $relations->deactivate($edit);
        $this->expectException(FileStorageAccessException::class);
        app(FileStorageV1::class)->authorizePrivateRead(new FilePrivateReadRequest(FileStorageOwnerType::User, $owner->id, $possession->id, null, $collaborator->id));
    }

    public function test_it_reserves_a_derivative_relation_without_generating_or_exposing_a_thumbnail(): void
    {
        $owner = User::factory()->create();
        $stored = $this->storeManaged($owner, 'source-content');
        $derivativeContent = StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', 'derivative-content'),
            'logicalSizeBytes' => strlen('derivative-content'),
            'detectedMimeType' => 'image/webp',
        ]);

        app(FileStorageV1::class)->reserveVersionDerivative(new ReserveFileVersionDerivativeRequest(
            sourceVersionId: $stored->versionId,
            derivativeContentId: $derivativeContent->id,
            derivativeKind: 'THUMBNAIL',
            derivativeKey: 'small',
        ));

        $this->assertDatabaseHas('file_versionDerivative', [
            'idSourceFileVersion' => $stored->versionId,
            'idFileContent' => $derivativeContent->id,
            'derivativeKind' => 'THUMBNAIL',
            'derivativeKey' => 'small',
            'state' => 'PENDING',
        ]);
        $this->assertSame(1, StoredFileVersionDerivative::query()->count());

        $this->expectException(FileStorageDerivativeException::class);

        app(FileStorageV1::class)->reserveVersionDerivative(new ReserveFileVersionDerivativeRequest(
            sourceVersionId: $stored->versionId,
            derivativeContentId: $stored->contentId,
            derivativeKind: 'THUMBNAIL',
            derivativeKey: 'original',
        ));
    }

    public function test_reconciliation_marks_broken_catalog_references_and_removes_expired_untracked_objects(): void
    {
        config(['file-storage.retention.orphanDays' => 1]);
        $backend = StoredFileStorageBackend::query()->create(['backendKey' => 'local-private', 'state' => 'ACTIVE']);
        $missingContent = $this->content('missing-catalog-object');
        $writingContent = $this->content('stale-writing-object');
        $missingObject = StoredFileStorageObject::query()->create([
            'idFileContent' => $missingContent->id,
            'idStorageBackend' => $backend->id,
            'storedSha256' => $missingContent->logicalSha256,
            'storageKey' => 'objects/sha256/aa/bb/missing.blob',
            'encoding' => 'IDENTITY',
            'storedSizeBytes' => $missingContent->logicalSizeBytes,
            'state' => 'ACTIVE',
        ]);
        $writingObject = StoredFileStorageObject::query()->create([
            'idFileContent' => $writingContent->id,
            'idStorageBackend' => $backend->id,
            'storedSha256' => $writingContent->logicalSha256,
            'storageKey' => 'objects/sha256/aa/bb/writing.blob',
            'encoding' => 'IDENTITY',
            'storedSizeBytes' => $writingContent->logicalSizeBytes,
            'state' => 'WRITING',
        ]);
        Storage::disk('file-private')->put($writingObject->storageKey, 'stale-writing-object');
        $orphanKey = 'objects/sha256/cc/dd/orphan.blob';
        Storage::disk('file-private')->put($orphanKey, 'untracked-orphan');
        touch(Storage::disk('file-private')->path($orphanKey), Carbon::now()->subDays(2)->getTimestamp());
        Carbon::setTestNow('2026-01-12 12:00:00');

        $result = app(FileStorageReconciliationService::class)->reconcile();

        $this->assertSame(1, $result->missingCatalogObjects);
        $this->assertSame(1, $result->staleWritingObjects);
        $this->assertSame(1, $result->deletedPhysicalOrphans);
        $this->assertSame('ORPHANED', $missingObject->fresh()->state);
        $this->assertSame('ORPHANED', $writingObject->fresh()->state);
        $this->assertNotNull($writingObject->fresh()->retentionUntil);
        Storage::disk('file-private')->assertMissing($orphanKey);
    }

    private function storeManaged(User $user, string $contents): StoredManagedVersion
    {
        return app(FileStorageV1::class)->storeManagedVersion(new StoreManagedVersionRequest(
            ownerType: FileStorageOwnerType::User,
            ownerId: $user->id,
            sourcePath: $this->sourceFile($contents),
            purpose: 'USER_PROFILE_AVATAR',
            displayName: 'avatar.txt',
            bindingKey: 'USER_PROFILE_AVATAR',
        ));
    }

    private function content(string $contents): StoredFileContent
    {
        return StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', $contents),
            'logicalSizeBytes' => strlen($contents),
            'detectedMimeType' => 'text/plain',
        ]);
    }

    private function sourceFile(string $contents): string
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.str()->uuid().'.txt';
        file_put_contents($path, $contents);

        return $path;
    }
}
