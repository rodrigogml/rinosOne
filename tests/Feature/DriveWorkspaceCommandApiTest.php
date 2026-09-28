<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFileOwnerUsage;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\User;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveWorkspaceCommandApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_resolves_names_moves_and_renames_personal_workspace_folders(): void
    {
        $user = User::factory()->create();
        $parent = $this->folder($user, 'Documents');
        $this->file($user, null, 'Report.pdf');

        $created = $this->actingAs($user)->postJson('/api/v1/drive/personal/folders', ['displayName' => 'Report.pdf'])
            ->assertCreated()->assertJsonPath('displayName', 'Report (2).pdf');
        $folderId = $created->json('id');

        $this->actingAs($user)->postJson("/api/v1/drive/personal/folders/{$folderId}/move", ['destinationFolderId' => $parent->id])
            ->assertOk()->assertJsonPath('parentFolderId', $parent->id);
        $this->actingAs($user)->patchJson("/api/v1/drive/personal/folders/{$folderId}", ['displayName' => 'Archive'])
            ->assertOk()->assertJsonPath('displayName', 'Archive');
    }

    public function test_it_refuses_an_unauthorized_command_without_partial_changes(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $folder = $this->folder($owner, 'Read only');
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'READ', $reader);

        $this->actingAs($reader)->postJson('/api/v1/drive/personal/folders', ['displayName' => 'Denied', 'parentFolderId' => $folder->id])
            ->assertForbidden()->assertJsonPath('error.code', 'DRIVE_ACCESS_DENIED');
        $this->assertDatabaseMissing('file_workspaceFolder', ['idUser' => $owner->id, 'displayName' => 'Denied']);
    }

    public function test_it_rejects_a_dangerous_folder_name_without_persisting_a_folder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/drive/personal/folders', ['displayName' => '../private'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DRIVE_INVALID_NAME');
        $this->assertDatabaseCount('file_workspaceFolder', 0);
    }

    public function test_it_trashes_restores_and_releases_a_folder_tree_atomically(): void
    {
        $user = User::factory()->create();
        $folder = $this->folder($user, 'Archive');
        $file = $this->file($user, $folder, 'Document.pdf');

        $this->actingAs($user)->postJson('/api/v1/drive/personal/items/trash', ['items' => [['type' => 'folder', 'id' => $folder->id]]])->assertNoContent();
        $this->assertSame('TRASHED', $folder->fresh()->state);
        $this->assertSame('TRASHED', $file->fresh()->state);

        $this->actingAs($user)->postJson('/api/v1/drive/personal/items/restore', ['items' => [['type' => 'folder', 'id' => $folder->id]]])->assertNoContent();
        $this->assertSame('ACTIVE', $folder->fresh()->state);

        $this->actingAs($user)->postJson('/api/v1/drive/personal/items/trash', ['items' => [['type' => 'folder', 'id' => $folder->id]]])->assertNoContent();
        $this->actingAs($user)->postJson('/api/v1/drive/personal/items/release', ['confirmation' => true, 'items' => [['type' => 'folder', 'id' => $folder->id]]])->assertNoContent();
        $this->assertDatabaseMissing('file_workspaceFolder', ['id' => $folder->id]);
        $this->assertSame('RELEASED', $file->fresh()->state);
    }

    public function test_it_refuses_cycles_and_rolls_back_a_mixed_selection_when_one_item_is_not_editable(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $parent = $this->folder($user, 'Parent');
        $child = WorkspaceFolder::query()->create(['idUser' => $user->id, 'idParentFolder' => $parent->id, 'displayName' => 'Child', 'state' => 'ACTIVE']);
        $sharedReadOnly = $this->folder($owner, 'Read only');
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $sharedReadOnly->id, AuthorizationScope::Personal), 'READ', $user);

        $this->actingAs($user)->postJson("/api/v1/drive/personal/folders/{$parent->id}/move", ['destinationFolderId' => $child->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DRIVE_INVALID_DESTINATION');
        $this->assertNull($parent->fresh()->idParentFolder);

        $this->actingAs($user)->postJson('/api/v1/drive/personal/items/trash', ['items' => [
            ['type' => 'folder', 'id' => $parent->id],
            ['type' => 'folder', 'id' => $sharedReadOnly->id],
        ]])->assertForbidden()->assertJsonPath('error.code', 'DRIVE_ACCESS_DENIED');
        $this->assertSame('ACTIVE', $parent->fresh()->state);
        $this->assertSame('ACTIVE', $child->fresh()->state);
    }

    private function folder(User $user, string $name): WorkspaceFolder
    {
        return WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => $name, 'state' => 'ACTIVE']);
    }

    private function file(User $user, ?WorkspaceFolder $folder, string $name): StoredFilePossession
    {
        $content = StoredFileContent::query()->create(['logicalSha256' => hash('sha256', $user->id.$name), 'logicalSizeBytes' => 10, 'detectedMimeType' => 'application/pdf']);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);
        StoredFileOwnerUsage::query()->create(['idUser' => $user->id, 'workspaceBytes' => 10, 'systemManagedBytes' => 0, 'trashBytes' => 0, 'totalBytes' => 10]);

        return StoredFilePossession::query()->create(['idFile' => $file->id, 'idCurrentFileVersion' => $version->id, 'idUser' => $user->id, 'idWorkspaceFolder' => $folder?->id, 'storageArea' => 'WORKSPACE', 'displayName' => $name, 'state' => 'ACTIVE', 'logicalSizeBytes' => 10]);
    }
}
