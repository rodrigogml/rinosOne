<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Contracts\FileStorage\V1\FileStorageV1;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DriveWorkspaceUploadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
        config()->set('file-storage.workspaceUpload', [
            'maximumFileBytes' => 1024 * 1024,
            'maximumBatchFiles' => 5,
            'maximumBatchBytes' => 2 * 1024 * 1024,
            'allowedMimeTypes' => ['*/*'],
            'temporaryRetentionMinutes' => 60,
        ]);
    }

    public function test_it_ingests_multiple_workspace_files_with_stable_individual_results_and_resolved_names(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/api/v1/drive/personal/uploads', [
            'files' => [
                UploadedFile::fake()->createWithContent('Notes.txt', 'first document'),
                UploadedFile::fake()->createWithContent('Notes.txt', 'second document'),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('storedCount', 2)
            ->assertJsonPath('rejectedCount', 0)
            ->assertJsonPath('results.0.state', 'STORED')
            ->assertJsonPath('results.0.item.displayName', 'Notes.txt')
            ->assertJsonPath('results.1.item.displayName', 'Notes (2).txt')
            ->assertJsonMissingPath('results.0.item.storageKey')
            ->assertJsonMissingPath('results.0.item.logicalSha256');

        $this->assertSame(2, StoredFilePossession::query()->where('idUser', $user->id)->where('state', 'ACTIVE')->count());
        $this->assertSame(strlen('first document') + strlen('second document'), (int) StoredFileOwnerUsage::query()->where('idUser', $user->id)->value('workspaceBytes'));
    }

    public function test_it_uses_the_real_mime_type_and_keeps_invalid_items_out_of_the_workspace(): void
    {
        $user = User::factory()->create();
        config()->set('file-storage.workspaceUpload.allowedMimeTypes', ['image/*']);

        $response = $this->actingAs($user)->post('/api/v1/drive/personal/uploads', [
            'files' => [
                UploadedFile::fake()->image('real.png', 20, 20),
                UploadedFile::fake()->createWithContent('spoofed.png', 'this is plain text'),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('storedCount', 1)
            ->assertJsonPath('rejectedCount', 1)
            ->assertJsonPath('results.0.state', 'STORED')
            ->assertJsonPath('results.1.error.code', 'DRIVE_UPLOAD_TYPE_NOT_ALLOWED');
        $this->assertSame(1, StoredFilePossession::query()->where('idUser', $user->id)->count());
    }

    public function test_it_rejects_an_entire_batch_over_the_configured_limit_without_creating_a_possession(): void
    {
        $user = User::factory()->create();
        config()->set('file-storage.workspaceUpload.maximumBatchFiles', 1);

        $this->actingAs($user)->post('/api/v1/drive/personal/uploads', [
            'files' => [UploadedFile::fake()->createWithContent('first.txt', 'a'), UploadedFile::fake()->createWithContent('second.txt', 'b')],
        ])->assertOk()
            ->assertJsonPath('storedCount', 0)
            ->assertJsonPath('rejectedCount', 2)
            ->assertJsonPath('results.0.error.code', 'DRIVE_UPLOAD_LIMIT_EXCEEDED')
            ->assertJsonPath('results.1.error.code', 'DRIVE_UPLOAD_LIMIT_EXCEEDED');

        $this->assertDatabaseCount('file_filePossession', 0);
    }

    public function test_it_rechecks_folder_edit_access_before_creating_a_possession(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Read only', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'READ', $reader);

        $this->actingAs($reader)->post('/api/v1/drive/personal/uploads', [
            'parentFolderId' => $folder->id,
            'files' => [UploadedFile::fake()->createWithContent('denied.txt', 'not allowed')],
        ])->assertOk()
            ->assertJsonPath('storedCount', 0)
            ->assertJsonPath('results.0.error.code', 'DRIVE_ACCESS_DENIED');

        $this->assertDatabaseMissing('file_filePossession', ['idUser' => $owner->id, 'displayName' => 'denied.txt']);
        $this->assertDatabaseCount('file_fileContent', 0);
    }

    public function test_it_does_not_activate_a_possession_when_edit_access_is_revoked_while_bytes_are_ingested(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $relations = app(AuthorizationResourceRelationService::class);
        $relation = $relations->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'EDIT', $editor);
        $realStorage = app(FileStorageV1::class);
        $storage = Mockery::mock(FileStorageV1::class);
        $storage->shouldReceive('ingestWorkspaceContent')->once()->andReturnUsing(function ($request) use ($realStorage, $relations, $relation) {
            $content = $realStorage->ingestWorkspaceContent($request);
            $relations->deactivate($relation);

            return $content;
        });
        $storage->shouldReceive('storeWorkspaceVersion')->never();
        app()->instance(FileStorageV1::class, $storage);

        $this->actingAs($editor)->post('/api/v1/drive/personal/uploads', [
            'parentFolderId' => $folder->id,
            'files' => [UploadedFile::fake()->createWithContent('revoked.txt', 'not activated')],
        ])->assertOk()
            ->assertJsonPath('storedCount', 0)
            ->assertJsonPath('results.0.error.code', 'DRIVE_ACCESS_DENIED');

        $this->assertDatabaseCount('file_filePossession', 0);
        $this->assertDatabaseCount('file_fileContent', 1);
    }
}
