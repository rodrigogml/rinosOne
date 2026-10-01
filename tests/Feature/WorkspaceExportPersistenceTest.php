<?php

namespace Tests\Feature;

use App\Models\FileStorage\WorkspaceExport;
use App\Jobs\FileStorage\GenerateWorkspaceExport;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileStorageBackend;
use App\Models\FileStorage\StoredFileStorageObject;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Domain\FileStorage\Exception\DriveWorkspaceProjectionException;
use App\Services\FileStorage\Drive\DriveWorkspaceExportService;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;
use Tests\TestCase;

class WorkspaceExportPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
    }

    public function test_it_persists_an_opaque_temporary_personal_export_without_workspace_quota_data(): void
    {
        $user = User::factory()->create();
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(),
            'idRequestingUser' => $user->id,
            'workspaceScope' => 'PERSONAL',
            'selectionManifest' => [['type' => 'file', 'id' => 42]],
            'state' => 'PENDING',
            'displayName' => 'Rinos Drive export.zip',
            'expiresAt' => now()->addHour(),
        ]);

        $this->assertSame('PENDING', $export->state);
        $this->assertSame([['type' => 'file', 'id' => 42]], $export->selectionManifest);
        $this->assertNull($export->storageKey);
        $this->assertDatabaseMissing('file_ownerUsage', ['idUser' => $user->id]);
    }

    public function test_only_the_requesting_user_can_read_or_cancel_a_personal_export(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $owner->id,
            'workspaceScope' => 'PERSONAL', 'selectionManifest' => [['type' => 'file', 'id' => 42]],
            'state' => 'PENDING', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->addHour(),
        ]);
        $service = app(DriveWorkspaceExportService::class);

        $this->assertSame('PENDING', $service->status($owner, DriveWorkspaceTarget::personal($owner->id), $export->publicId)->state);
        $this->assertSame('CANCELLED', $service->cancel($owner, DriveWorkspaceTarget::personal($owner->id), $export->publicId)->state);

        $this->expectException(DriveWorkspaceCommandException::class);
        $service->status($other, DriveWorkspaceTarget::personal($other->id), $export->publicId);
    }

    public function test_a_cancelled_export_job_is_idempotently_ignored_without_creating_a_download(): void
    {
        $user = User::factory()->create();
        $first = $this->workspaceFile($user, 'first', 'First.txt');
        $second = $this->workspaceFile($user, 'second', 'Second.txt');
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $user->id,
            'workspaceScope' => 'PERSONAL',
            'selectionManifest' => [['type' => 'file', 'id' => $first->id], ['type' => 'file', 'id' => $second->id]],
            'state' => 'PENDING', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->addHour(),
        ]);
        $service = app(DriveWorkspaceExportService::class);

        $service->cancel($user, DriveWorkspaceTarget::personal($user->id), $export->publicId);
        $service->generate($export->publicId);

        $export->refresh();
        $this->assertSame('CANCELLED', $export->state);
        $this->assertNull($export->storageKey);
        Storage::disk('file-private')->assertMissing('workspace-exports/'.$export->publicId.'.zip');
    }

    public function test_an_expired_export_is_not_available_to_its_requester(): void
    {
        $user = User::factory()->create();
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $user->id,
            'workspaceScope' => 'PERSONAL', 'selectionManifest' => [['type' => 'file', 'id' => 42]],
            'state' => 'PENDING', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->subSecond(),
        ]);

        $this->expectException(DriveWorkspaceCommandException::class);
        app(DriveWorkspaceExportService::class)->status($user, DriveWorkspaceTarget::personal($user->id), $export->publicId);
    }

    public function test_a_ready_export_without_private_bytes_is_not_downloadable(): void
    {
        $user = User::factory()->create();
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $user->id,
            'workspaceScope' => 'PERSONAL', 'selectionManifest' => [], 'state' => 'READY',
            'displayName' => 'Rinos Drive export.zip', 'storageKey' => 'workspace-exports/missing.zip', 'expiresAt' => now()->addHour(),
        ]);

        $this->actingAs($user)->getJson("/api/v1/drive/personal/exports/{$export->publicId}/download")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DRIVE_EXPORT_UNAVAILABLE');
    }

    public function test_expired_export_records_are_purged_without_touching_workspace_quota(): void
    {
        $user = User::factory()->create();
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $user->id,
            'workspaceScope' => 'PERSONAL', 'selectionManifest' => [['type' => 'file', 'id' => 42]],
            'state' => 'FAILED', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->subMinute(),
        ]);

        app(DriveWorkspaceExportService::class)->purgeExpired();
        app(DriveWorkspaceExportService::class)->purgeExpired();

        $this->assertDatabaseMissing('file_workspaceExport', ['id' => $export->id]);
        $this->assertDatabaseMissing('file_ownerUsage', ['idUser' => $user->id]);
    }

    public function test_cleanup_removes_an_aged_unregistered_temporary_archive(): void
    {
        Storage::disk('file-private')->put('workspace-exports/orphan.zip', 'orphaned archive');
        Carbon::setTestNow(now()->addMinutes(61));
        try {
            app(DriveWorkspaceExportService::class)->purgeExpired();
            Storage::disk('file-private')->assertMissing('workspace-exports/orphan.zip');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_it_generates_a_private_zip_with_unique_entry_names_and_all_selected_content(): void
    {
        $user = User::factory()->create();
        $first = $this->workspaceFile($user, 'first export contents', 'Report.txt');
        $second = $this->workspaceFile($user, 'second export contents', 'Report.txt');
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $user->id,
            'workspaceScope' => 'PERSONAL',
            'selectionManifest' => [['type' => 'file', 'id' => $first->id], ['type' => 'file', 'id' => $second->id]],
            'state' => 'PENDING', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->addHour(),
        ]);

        app(DriveWorkspaceExportService::class)->generate($export->publicId);

        $export->refresh();
        $this->assertSame('READY', $export->state);
        $this->assertNotNull($export->storageKey);
        $this->assertGreaterThan(0, $export->storedSizeBytes);
        Storage::disk('file-private')->assertExists($export->storageKey);

        $archivePath = tempnam(sys_get_temp_dir(), 'rinos-drive-export-test-');
        file_put_contents($archivePath, Storage::disk('file-private')->get($export->storageKey));
        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($archivePath) === true);
        $this->assertSame('first export contents', $archive->getFromName('Report.txt'));
        $this->assertSame('second export contents', $archive->getFromName('Report (2).txt'));
        $archive->close();
        unlink($archivePath);
    }

    public function test_it_rejects_an_archive_that_exceeds_the_configured_uncompressed_limit_without_publishing_a_partial_zip(): void
    {
        config()->set('file-storage.workspaceExport.maximumBytes', 5);
        $user = User::factory()->create();
        $first = $this->workspaceFile($user, 'first export contents', 'First.txt');
        $second = $this->workspaceFile($user, 'second export contents', 'Second.txt');
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $user->id,
            'workspaceScope' => 'PERSONAL',
            'selectionManifest' => [['type' => 'file', 'id' => $first->id], ['type' => 'file', 'id' => $second->id]],
            'state' => 'PENDING', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->addHour(),
        ]);

        try {
            app(DriveWorkspaceExportService::class)->generate($export->publicId);
            $this->fail('The configured export size limit must reject the archive.');
        } catch (DriveWorkspaceCommandException $exception) {
            $this->assertSame('DRIVE_EXPORT_LIMIT_EXCEEDED', $exception->getMessage());
        }

        $export->refresh();
        $this->assertSame('FAILED', $export->state);
        $this->assertNull($export->storageKey);
        Storage::disk('file-private')->assertMissing('workspace-exports/'.$export->publicId.'.zip');
    }

    public function test_it_exposes_an_authenticated_export_request_and_queues_only_the_opaque_identifier(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $first = $this->workspaceFile($user, 'first', 'First.txt');
        $second = $this->workspaceFile($user, 'second', 'Second.txt');

        $response = $this->actingAs($user)->postJson('/api/v1/drive/personal/exports', [
            'items' => [['type' => 'file', 'id' => $first->id], ['type' => 'file', 'id' => $second->id]],
        ]);

        $response->assertAccepted()->assertJsonPath('state', 'PENDING');
        $publicId = $response->json('exportId');
        $this->assertIsString($publicId);
        Queue::assertPushed(GenerateWorkspaceExport::class, fn (GenerateWorkspaceExport $job): bool => $job->exportId === $publicId);
        $this->assertDatabaseHas('file_workspaceExport', ['publicId' => $publicId, 'idRequestingUser' => $user->id, 'state' => 'PENDING']);
    }

    public function test_it_accepts_one_readable_folder_for_a_zip_export(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $folder = WorkspaceFolder::query()->create([
            'idUser' => $user->id,
            'displayName' => 'Projetos',
            'state' => 'ACTIVE',
        ]);
        $this->workspaceFile($user, 'folder export contents', 'Readme.txt', $folder);

        $response = $this->actingAs($user)->postJson('/api/v1/drive/personal/exports', [
            'items' => [['type' => 'folder', 'id' => $folder->id]],
        ]);

        $response->assertAccepted()->assertJsonPath('state', 'PENDING');
        $publicId = $response->json('exportId');
        Queue::assertPushed(GenerateWorkspaceExport::class, fn (GenerateWorkspaceExport $job): bool => $job->exportId === $publicId);
        $this->assertDatabaseHas('file_workspaceExport', [
            'publicId' => $publicId,
            'idRequestingUser' => $user->id,
            'selectionManifest' => json_encode([['type' => 'folder', 'id' => $folder->id]]),
        ]);
    }

    public function test_it_revalidates_and_exports_a_personal_folder_explicitly_shared_with_the_requester(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $file = $this->workspaceFile($owner, 'shared export contents', 'Shared.txt', $folder);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'READ', $collaborator);
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $collaborator->id,
            'workspaceScope' => 'PERSONAL', 'selectionManifest' => [['type' => 'folder', 'id' => $folder->id]],
            'state' => 'PENDING', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->addHour(),
        ]);

        app(DriveWorkspaceExportService::class)->generate($export->publicId);

        $export->refresh();
        $this->assertSame('READY', $export->state);
        $archivePath = tempnam(sys_get_temp_dir(), 'rinos-drive-shared-export-');
        file_put_contents($archivePath, Storage::disk('file-private')->get($export->storageKey));
        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($archivePath) === true);
        $this->assertSame('shared export contents', $archive->getFromName('Shared/Shared.txt'));
        $archive->close();
        unlink($archivePath);
    }

    public function test_a_revoked_folder_relation_prevents_a_pending_export_from_publishing_bytes(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $this->workspaceFile($owner, 'shared export contents', 'Shared.txt', $folder);
        $relation = app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'READ', $collaborator);
        $export = WorkspaceExport::query()->create([
            'publicId' => (string) str()->ulid(), 'idRequestingUser' => $collaborator->id,
            'workspaceScope' => 'PERSONAL', 'selectionManifest' => [['type' => 'folder', 'id' => $folder->id]],
            'state' => 'PENDING', 'displayName' => 'Rinos Drive export.zip', 'expiresAt' => now()->addHour(),
        ]);
        app(AuthorizationResourceRelationService::class)->deactivate($relation);

        $this->expectException(DriveWorkspaceProjectionException::class);
        try {
            app(DriveWorkspaceExportService::class)->generate($export->publicId);
        } finally {
            $export->refresh();
            $this->assertSame('FAILED', $export->state);
            $this->assertNull($export->storageKey);
            Storage::disk('file-private')->assertMissing('workspace-exports/'.$export->publicId.'.zip');
        }
    }

    private function workspaceFile(User $user, string $contents, string $displayName, ?WorkspaceFolder $folder = null): StoredFilePossession
    {
        $content = StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', $contents), 'logicalSizeBytes' => strlen($contents),
            'detectedMimeType' => 'text/plain', 'declaredExtension' => 'txt',
        ]);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);
        $backend = StoredFileStorageBackend::query()->firstOrCreate(['backendKey' => 'local-private'], ['state' => 'ACTIVE']);
        $storageKey = 'objects/test/'.str()->uuid().'.blob';
        StoredFileStorageObject::query()->create([
            'idFileContent' => $content->id, 'idStorageBackend' => $backend->id, 'storedSha256' => $content->logicalSha256,
            'storageKey' => $storageKey, 'encoding' => 'IDENTITY', 'storedSizeBytes' => $content->logicalSizeBytes, 'state' => 'ACTIVE',
        ]);
        Storage::disk('file-private')->put($storageKey, $contents);

        return StoredFilePossession::query()->create([
            'idFile' => $file->id, 'idCurrentFileVersion' => $version->id, 'idUser' => $user->id,
            'idWorkspaceFolder' => $folder?->id, 'storageArea' => 'WORKSPACE', 'displayName' => $displayName, 'state' => 'ACTIVE', 'logicalSizeBytes' => $content->logicalSizeBytes,
        ]);
    }
}
