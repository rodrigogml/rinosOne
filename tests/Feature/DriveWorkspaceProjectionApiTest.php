<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRestriction;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveWorkspaceProjectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_tree_location_and_details_expose_only_authorized_safe_workspace_data(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $shared = $this->folder($owner, 'Shared');
        $descendant = $this->folder($owner, 'Contracts', $shared->id);
        $private = $this->folder($owner, 'Private');
        $file = $this->file($owner, $descendant, 'Contract.pdf', 'application/pdf');
        $this->file($owner, $private, 'Secret.pdf', 'application/pdf');
        StoredFilePossession::query()->create([
            'idFile' => $file->idFile,
            'idCurrentFileVersion' => $file->idCurrentFileVersion,
            'idUser' => $owner->id,
            'storageArea' => 'SYSTEM_MANAGED',
            'purpose' => 'PROFILE',
            'displayName' => 'avatar.png',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 42,
        ]);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $shared->id, AuthorizationScope::Personal), 'READ', $collaborator);

        $this->actingAs($collaborator)
            ->getJson('/api/v1/drive/personal/tree')
            ->assertOk()
            ->assertJsonFragment(['id' => $shared->id, 'displayName' => 'Shared'])
            ->assertJsonFragment(['id' => $descendant->id, 'displayName' => 'Contracts'])
            ->assertJsonMissing(['id' => $private->id, 'displayName' => 'Private']);

        $this->actingAs($collaborator)
            ->getJson("/api/v1/drive/personal/folders/{$descendant->id}")
            ->assertOk()
            ->assertJsonPath('location.id', $descendant->id)
            ->assertJsonFragment(['id' => $file->id, 'displayName' => 'Contract.pdf'])
            ->assertJsonMissing(['logicalSha256' => hash('sha256', 'Contract.pdf')]);

        $this->actingAs($collaborator)
            ->getJson("/api/v1/drive/personal/items/file/{$file->id}/details")
            ->assertOk()
            ->assertJsonPath('item.detectedMimeType', 'application/pdf')
            ->assertJsonPath('metadata', [])
            ->assertJsonMissing(['storageKey' => 'never-exposed']);

        $this->actingAs($collaborator)->getJson("/api/v1/drive/personal/folders/{$private->id}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DRIVE_ACCESS_DENIED');
    }

    public function test_edit_relation_includes_read_and_revocation_removes_drive_visibility(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $folder = $this->folder($owner, 'Editable');
        $relation = app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'EDIT', $editor);

        $this->actingAs($editor)->getJson('/api/v1/drive/personal/tree')
            ->assertOk()
            ->assertJsonFragment(['id' => $folder->id, 'capabilities' => ['read' => true, 'edit' => true, 'trash' => true]]);

        app(AuthorizationResourceRelationService::class)->deactivate($relation);

        $this->actingAs($editor)->getJson('/api/v1/drive/personal/tree')
            ->assertOk()
            ->assertJsonPath('folders', []);
    }

    public function test_tenant_administrator_reads_all_but_member_sees_only_granted_branch_and_restriction_wins(): void
    {
        $administrator = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => 'ACTIVE']);
        TenantMembership::query()->insert([
            ['idTenant' => $tenant->id, 'idUser' => $administrator->id, 'state' => 'ACTIVE'],
            ['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE'],
        ]);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $shared = WorkspaceFolder::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $private = WorkspaceFolder::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('tenant.folder', $shared->id, AuthorizationScope::Tenant, $tenant->id), 'READ', $member);

        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/drive/tree")
            ->assertOk()
            ->assertJsonFragment(['id' => $shared->id])
            ->assertJsonFragment(['id' => $private->id]);
        $this->actingAs($member)->getJson("/api/v1/tenants/{$tenant->id}/drive/tree")
            ->assertOk()
            ->assertJsonFragment(['id' => $shared->id])
            ->assertJsonMissing(['id' => $private->id]);

        AuthorizationRestriction::query()->create([
            'idPermission' => AuthorizationPermission::query()->where('key', 'tenant.folder.read')->firstOrFail()->id,
            'idUser' => $administrator->id,
            'idTenant' => $tenant->id,
            'scope' => 'TENANT',
            'active' => true,
        ]);
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/drive/locations/root")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DRIVE_ACCESS_DENIED');
    }

    public function test_personal_trash_exposes_only_the_authenticated_users_trashed_items(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $folder = $this->folder($owner, 'Archived');
        $file = $this->file($owner, $folder, 'Archived.pdf', 'application/pdf');
        $folder->update(['state' => 'TRASHED', 'purgeAfter' => now()->addDays(30)]);
        $file->update(['state' => 'TRASHED', 'purgeAfter' => now()->addDays(30)]);
        $otherFolder = $this->folder($otherUser, 'Other archive');
        $otherFolder->update(['state' => 'TRASHED', 'purgeAfter' => now()->addDays(30)]);

        $this->actingAs($owner)->getJson('/api/v1/drive/personal/trash')
            ->assertOk()
            ->assertJsonFragment(['id' => $folder->id, 'displayName' => 'Archived'])
            ->assertJsonFragment(['id' => $file->id, 'displayName' => 'Archived.pdf'])
            ->assertJsonMissing(['id' => $otherFolder->id, 'displayName' => 'Other archive']);
    }

    private function folder(User $owner, string $displayName, ?int $parentFolderId = null): WorkspaceFolder
    {
        return WorkspaceFolder::query()->create(['idUser' => $owner->id, 'idParentFolder' => $parentFolderId, 'displayName' => $displayName, 'state' => 'ACTIVE']);
    }

    private function file(User $owner, WorkspaceFolder $folder, string $displayName, string $mimeType): StoredFilePossession
    {
        $content = StoredFileContent::query()->create(['logicalSha256' => hash('sha256', $displayName), 'logicalSizeBytes' => 42, 'detectedMimeType' => $mimeType, 'declaredExtension' => 'pdf']);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);

        return StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $owner->id,
            'idWorkspaceFolder' => $folder->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => $displayName,
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 42,
        ]);
    }
}
