<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Infrastructure\FileStorage\Authorization\WorkspaceFileAuthorizationResourceAdapter;
use App\Models\AuthorizationGroup;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use App\Services\FileStorage\Drive\DriveWorkspaceExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Tests\TestCase;

class WorkspaceFileAuthorizationResourceAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_only_an_active_workspace_file_as_a_direct_read_only_resource(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $possession = $this->file($owner);
        $privatePossession = $this->file($owner);
        $resource = new ResourceReference('personal.file', $possession->id, AuthorizationScope::Personal);
        $registry = app(AuthorizationResourceRegistry::class);
        $relations = app(AuthorizationResourceRelationService::class);

        $registry->assertAvailable($resource);
        $adapter = new WorkspaceFileAuthorizationResourceAdapter(AuthorizationScope::Personal);
        $this->assertSame(['personal.file.read'], $adapter->supportedActions());
        $this->assertSame(['READ'], $adapter->supportedRelations());
        $this->assertFalse($adapter->allowsGroupRelations());
        $this->assertSame([$possession->id], $adapter->inheritedResourceIds($resource));

        $relations->create($resource, 'READ', $reader);
        $this->assertTrue(app(AuthorizationService::class)->check($reader, 'personal.file.read', AuthorizationScope::Personal, resource: $resource)->allowed);
        $this->actingAs($reader)
            ->getJson("/api/v1/drive/shared-with-me/files/{$possession->id}/details")
            ->assertOk()
            ->assertJsonPath('item.id', $possession->id)
            ->assertJsonPath('item.capabilities.read', true)
            ->assertJsonPath('item.capabilities.edit', false)
            ->assertJsonPath('item.capabilities.trash', false)
            ->assertJsonMissing(['logicalSha256' => $possession->currentVersion->content->logicalSha256]);
        $this->actingAs($reader)
            ->getJson("/api/v1/drive/shared-with-me/files/{$privatePossession->id}/details")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DRIVE_LOCATION_NOT_FOUND');
        $this->actingAs($reader)
            ->postJson("/api/v1/drive/personal/files/{$possession->id}/move", ['destinationFolderId' => null])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'DRIVE_ACCESS_DENIED');

        $possession->forceFill(['state' => 'TRASHED'])->save();
        $this->expectException(LogicException::class);
        $registry->assertAvailable($resource);
    }

    public function test_it_rejects_system_managed_files_incompatible_tenant_contexts_and_group_relations(): void
    {
        $owner = User::factory()->create();
        $personal = $this->file($owner, storageArea: 'SYSTEM_MANAGED');
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Other', 'state' => 'ACTIVE']);
        $tenantPossession = $this->file(null, tenant: $tenant);
        $registry = app(AuthorizationResourceRegistry::class);

        foreach ([
            new ResourceReference('personal.file', $personal->id, AuthorizationScope::Personal),
            new ResourceReference('tenant.file', $tenantPossession->id, AuthorizationScope::Tenant, $otherTenant->id),
        ] as $resource) {
            try {
                $registry->assertAvailable($resource);
                $this->fail('An unavailable file resource must be rejected.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $group = AuthorizationGroup::query()->create(['displayName' => 'Readers', 'scope' => 'PERSONAL', 'active' => true]);
        $activePersonal = $this->file($owner);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires a user subject');
        app(AuthorizationResourceRelationService::class)->create(
            new ResourceReference('personal.file', $activePersonal->id, AuthorizationScope::Personal),
            'READ',
            group: $group,
        );
    }

    public function test_it_queues_a_direct_file_export_only_for_authorized_files_from_the_same_workspace(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $first = $this->file($owner);
        $second = $this->file($owner);
        $relations = app(AuthorizationResourceRelationService::class);
        foreach ([$first, $second] as $possession) {
            $relations->create(new ResourceReference('personal.file', $possession->id, AuthorizationScope::Personal), 'READ', $reader);
        }

        $export = app(DriveWorkspaceExportService::class)->requestDirectFiles($reader, [
            ['type' => 'file', 'id' => $first->id],
            ['type' => 'file', 'id' => $second->id],
        ]);

        $this->assertSame('PENDING', $export->state);
        $this->assertSame([
            ['type' => 'file', 'id' => $first->id, 'directShare' => true],
            ['type' => 'file', 'id' => $second->id, 'directShare' => true],
        ], $export->selectionManifest);
        $this->actingAs($reader)
            ->postJson('/api/v1/drive/shared-with-me/exports', [
                'items' => [
                    ['type' => 'file', 'id' => $first->id],
                    ['type' => 'file', 'id' => $second->id],
                ],
            ])
            ->assertAccepted()
            ->assertJsonPath('state', 'PENDING')
            ->assertJsonMissing(['displayName' => $first->displayName]);
    }

    private function file(?User $user, ?Tenant $tenant = null, string $storageArea = 'WORKSPACE'): StoredFilePossession
    {
        $content = StoredFileContent::query()->create([
            'logicalSha256' => hash('sha256', (string) str()->uuid()),
            'logicalSizeBytes' => 42,
            'detectedMimeType' => 'application/pdf',
        ]);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);

        return StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $user?->id,
            'idTenant' => $tenant?->id,
            'storageArea' => $storageArea,
            'purpose' => $storageArea === 'SYSTEM_MANAGED' ? 'PROFILE_AVATAR' : null,
            'displayName' => 'Report.pdf',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 42,
        ]);
    }
}
