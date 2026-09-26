<?php

namespace Tests\Feature;

use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\FileStorage\Exception\FileStorageVersionException;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\FileStorage\WorkspaceFolderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceFolderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_creates_moves_renames_and_lists_only_the_requested_workspace_tree(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $service = app(WorkspaceFolderService::class);
        $root = $service->create(FileStorageOwnerType::User, $user->id, 'Documents');
        $child = $service->create(FileStorageOwnerType::User, $user->id, 'Invoices', $root->id);
        $other = $service->create(FileStorageOwnerType::User, $otherUser->id, 'Private');

        $service->move(FileStorageOwnerType::User, $user->id, $child->id, null);
        $service->rename(FileStorageOwnerType::User, $user->id, $child->id, 'Receipts');
        $folders = $service->listTree(FileStorageOwnerType::User, $user->id);

        $this->assertNull($child->fresh()->idParentFolder);
        $this->assertSame('Receipts', $child->fresh()->displayName);
        $this->assertSame([$root->id, $child->id], $folders->pluck('id')->sort()->values()->all());
        $this->assertNotContains($other->id, $folders->pluck('id')->all());
    }

    public function test_it_trashes_and_restores_the_complete_folder_set_with_its_possessions(): void
    {
        $user = User::factory()->create();
        $service = app(WorkspaceFolderService::class);
        $root = $service->create(FileStorageOwnerType::User, $user->id, 'Documents');
        $child = $service->create(FileStorageOwnerType::User, $user->id, 'Invoices', $root->id);
        $possession = $this->createWorkspacePossession($user, $child);

        $service->trash(FileStorageOwnerType::User, $user->id, $root->id);

        $this->assertSame('TRASHED', $root->fresh()->state);
        $this->assertSame('TRASHED', $child->fresh()->state);
        $this->assertSame('2026-01-31 12:00:00', $root->fresh()->purgeAfter?->format('Y-m-d H:i:s'));
        $this->assertSame('TRASHED', $possession->fresh()->state);
        $this->assertSame(0, StoredFileOwnerUsage::query()->where('idUser', $user->id)->value('workspaceBytes'));
        $this->assertSame(11, StoredFileOwnerUsage::query()->where('idUser', $user->id)->value('trashBytes'));

        $service->restore(FileStorageOwnerType::User, $user->id, $root->id);

        $this->assertSame('ACTIVE', $root->fresh()->state);
        $this->assertSame('ACTIVE', $child->fresh()->state);
        $this->assertNull($root->fresh()->purgeAfter);
        $this->assertSame('ACTIVE', $possession->fresh()->state);
        $this->assertSame(11, StoredFileOwnerUsage::query()->where('idUser', $user->id)->value('workspaceBytes'));
        $this->assertSame(0, StoredFileOwnerUsage::query()->where('idUser', $user->id)->value('trashBytes'));
    }

    public function test_it_refuses_restore_when_any_possession_has_expired_without_partially_restoring_the_tree(): void
    {
        $user = User::factory()->create();
        $service = app(WorkspaceFolderService::class);
        $root = $service->create(FileStorageOwnerType::User, $user->id, 'Documents');
        $possession = $this->createWorkspacePossession($user, $root);
        $service->trash(FileStorageOwnerType::User, $user->id, $root->id);
        $possession->forceFill(['purgeAfter' => now()->subSecond()])->save();

        $this->expectException(FileStorageVersionException::class);
        $this->expectExceptionMessage('can no longer be restored');

        try {
            $service->restore(FileStorageOwnerType::User, $user->id, $root->id);
        } finally {
            $this->assertSame('TRASHED', $root->fresh()->state);
            $this->assertSame('TRASHED', $possession->fresh()->state);
        }
    }

    private function createWorkspacePossession(User $user, WorkspaceFolder $folder): StoredFilePossession
    {
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $content = StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', 'folder-test-'.$file->id),
            'logicalSizeBytes' => 11,
            'detectedMimeType' => 'text/plain',
        ]);
        $version = StoredFileVersion::query()->create([
            'idFile' => $file->id,
            'idFileContent' => $content->id,
            'versionNumber' => 1,
        ]);
        $possession = StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $user->id,
            'idWorkspaceFolder' => $folder->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'document.txt',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 11,
        ]);
        StoredFileOwnerUsage::query()->create([
            'idUser' => $user->id,
            'workspaceBytes' => 11,
            'systemManagedBytes' => 0,
            'trashBytes' => 0,
            'totalBytes' => 11,
        ]);

        return $possession;
    }
}
