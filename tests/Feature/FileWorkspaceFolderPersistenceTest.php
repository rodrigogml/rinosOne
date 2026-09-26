<?php

namespace Tests\Feature;

use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class FileWorkspaceFolderPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_requires_one_owner_and_a_parent_from_the_same_workspace(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => 'ACTIVE']);
        $parent = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        $child = WorkspaceFolder::query()->create(['idParentFolder' => $parent->id, 'idUser' => $user->id, 'displayName' => 'Invoices', 'state' => 'ACTIVE']);

        $this->assertSame($parent->id, $child->idParentFolder);
        $this->expectException(LogicException::class);
        WorkspaceFolder::query()->create(['idParentFolder' => $parent->id, 'idTenant' => $tenant->id, 'displayName' => 'Invalid', 'state' => 'ACTIVE']);
    }

    public function test_folder_rejects_cycles_and_duplicate_active_sibling_names(): void
    {
        $user = User::factory()->create();
        $root = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        $child = WorkspaceFolder::query()->create(['idParentFolder' => $root->id, 'idUser' => $user->id, 'displayName' => 'Invoices', 'state' => 'ACTIVE']);

        try {
            WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
            $this->fail('An active sibling with the same name must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('unique among active siblings', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be moved below one of its descendants');
        $root->forceFill(['idParentFolder' => $child->id])->save();
    }

    public function test_workspace_possession_requires_a_folder_in_its_own_workspace(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $otherUser->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $content = StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', 'workspace-folder-test'),
            'logicalSizeBytes' => 21,
            'detectedMimeType' => 'text/plain',
        ]);
        $version = StoredFileVersion::query()->create([
            'idFile' => $file->id,
            'idFileContent' => $content->id,
            'versionNumber' => 1,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('folder must belong to the same workspace');
        StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $user->id,
            'idWorkspaceFolder' => $folder->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'document.txt',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 21,
        ]);
    }
}
