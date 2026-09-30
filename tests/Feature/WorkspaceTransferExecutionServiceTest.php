<?php

namespace Tests\Feature;

use App\Domain\FileStorage\Drive\DriveWorkspaceTarget;
use App\Domain\FileStorage\Drive\WorkspaceTransferMode;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\FileStorage\Drive\DriveTransferRequestService;
use App\Services\FileStorage\Drive\WorkspaceTransferExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkspaceTransferExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_copies_a_logical_file_reference_without_copying_its_content(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $source = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Source', 'state' => 'ACTIVE']);
        $destination = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Destination', 'state' => 'ACTIVE']);
        $content = StoredFileContent::query()->create(['logicalSha256' => hash('sha256', 'transfer'), 'logicalSizeBytes' => 24, 'detectedMimeType' => 'text/plain']);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);
        $possession = StoredFilePossession::query()->create(['idFile' => $file->id, 'idCurrentFileVersion' => $version->id, 'idUser' => $user->id, 'idWorkspaceFolder' => $source->id, 'storageArea' => 'WORKSPACE', 'displayName' => 'note.txt', 'state' => 'ACTIVE', 'logicalSizeBytes' => 24]);
        $target = DriveWorkspaceTarget::personal($user->id);
        $transfer = app(DriveTransferRequestService::class)->request($user, $target, $target, [['type' => 'file', 'id' => $possession->id]], WorkspaceTransferMode::Copy, $destination->id);

        $completed = app(WorkspaceTransferExecutionService::class)->execute($user, $transfer);
        $copy = StoredFilePossession::query()->where('idWorkspaceFolder', $destination->id)->sole();
        $this->assertSame('COMPLETED', $completed->state->value);
        $this->assertSame($possession->idFile, $copy->idFile);
        $this->assertSame($possession->idCurrentFileVersion, $copy->idCurrentFileVersion);
        $this->assertSame('ACTIVE', $possession->refresh()->state);
    }

    public function test_it_copies_a_folder_tree_before_releasing_the_source_for_move(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $source = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Source', 'state' => 'ACTIVE']);
        $child = WorkspaceFolder::query()->create(['idUser' => $user->id, 'idParentFolder' => $source->id, 'displayName' => 'Child', 'state' => 'ACTIVE']);
        $destination = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Destination', 'state' => 'ACTIVE']);
        $possession = $this->file($user, $child->id, 30);
        StoredFileOwnerUsage::query()->create(['idUser' => $user->id, 'workspaceBytes' => 30, 'systemManagedBytes' => 0, 'trashBytes' => 0, 'totalBytes' => 30]);
        $target = DriveWorkspaceTarget::personal($user->id);
        $transfer = app(DriveTransferRequestService::class)->request($user, $target, $target, [['type' => 'folder', 'id' => $source->id]], WorkspaceTransferMode::Move, $destination->id);

        $completed = app(WorkspaceTransferExecutionService::class)->execute($user, $transfer);
        $copiedRoot = WorkspaceFolder::query()->where('idParentFolder', $destination->id)->sole();
        $copiedChild = WorkspaceFolder::query()->where('idParentFolder', $copiedRoot->id)->sole();
        $copiedFile = StoredFilePossession::query()->where('idWorkspaceFolder', $copiedChild->id)->sole();

        $this->assertSame('COMPLETED', $completed->state->value);
        $this->assertSame($possession->idFile, $copiedFile->idFile);
        $this->assertDatabaseMissing('file_workspaceFolder', ['id' => $source->id]);
        $this->assertSame('RELEASED', $possession->refresh()->state);
    }

    private function file(User $user, int $folderId, int $bytes): StoredFilePossession
    {
        $content = StoredFileContent::query()->create(['logicalSha256' => hash('sha256', (string) str()->uuid()), 'logicalSizeBytes' => $bytes, 'detectedMimeType' => 'text/plain']);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);

        return StoredFilePossession::query()->create(['idFile' => $file->id, 'idCurrentFileVersion' => $version->id, 'idUser' => $user->id, 'idWorkspaceFolder' => $folderId, 'storageArea' => 'WORKSPACE', 'displayName' => 'tree.txt', 'state' => 'ACTIVE', 'logicalSizeBytes' => $bytes]);
    }
}
