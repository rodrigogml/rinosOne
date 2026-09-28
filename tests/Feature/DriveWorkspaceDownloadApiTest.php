<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\FileStorage\StoredFile;
use App\Models\FileStorage\StoredFileContent;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\StoredFileStorageBackend;
use App\Models\FileStorage\StoredFileStorageObject;
use App\Models\FileStorage\StoredFileVersion;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriveWorkspaceDownloadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('file-private');
    }

    public function test_it_streams_a_personal_workspace_file_as_an_attachment_without_storage_details(): void
    {
        $owner = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        $file = $this->file('personal download', 'Report.txt', folder: $folder);

        $response = $this->actingAs($owner)->get("/api/v1/drive/personal/files/{$file->id}/download");

        $response->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-disposition', 'attachment; filename=Report.txt');
        $this->assertSame('personal download', $response->streamedContent());
        $this->assertStringNotContainsString('objects/', (string) $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString('storageKey', $response->streamedContent());
    }

    public function test_it_allows_a_shared_folder_reader_then_denies_the_download_after_revocation(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $owner->id, 'displayName' => 'Shared', 'state' => 'ACTIVE']);
        $file = $this->file('shared download', 'Shared.txt', folder: $folder);
        $relation = app(AuthorizationResourceRelationService::class)->create(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal), 'READ', $reader);

        $this->actingAs($reader)->get("/api/v1/drive/personal/files/{$file->id}/download")
            ->assertOk();
        app(AuthorizationResourceRelationService::class)->deactivate($relation);
        $this->actingAs($reader)->getJson("/api/v1/drive/personal/files/{$file->id}/download")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DRIVE_LOCATION_NOT_FOUND');
    }

    public function test_a_work_administrator_can_download_a_root_file_but_trash_and_missing_bytes_are_unavailable(): void
    {
        $administrator = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $administrator->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $rootFile = $this->file('work root', 'Work.txt', tenant: $tenant);
        $trashedFile = $this->file('trash', 'Trash.txt', tenant: $tenant, state: 'TRASHED');
        $missingFile = $this->file('missing', 'Missing.txt', tenant: $tenant, writeBytes: false);

        $this->actingAs($administrator)->get("/api/v1/tenants/{$tenant->id}/drive/files/{$rootFile->id}/download")
            ->assertOk();
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/drive/files/{$trashedFile->id}/download")
            ->assertNotFound()->assertJsonPath('error.code', 'DRIVE_LOCATION_NOT_FOUND');
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/drive/files/{$missingFile->id}/download")
            ->assertNotFound()->assertJsonPath('error.code', 'DRIVE_LOCATION_NOT_FOUND');
    }

    private function file(string $contents, string $name, ?WorkspaceFolder $folder = null, ?Tenant $tenant = null, string $state = 'ACTIVE', bool $writeBytes = true): StoredFilePossession
    {
        $content = StoredFileContent::query()->create(['logicalSha256' => hash('sha256', $contents), 'logicalSizeBytes' => strlen($contents), 'detectedMimeType' => 'text/plain', 'declaredExtension' => 'txt']);
        $file = StoredFile::query()->create(['fileUuid' => (string) str()->uuid()]);
        $version = StoredFileVersion::query()->create(['idFile' => $file->id, 'idFileContent' => $content->id, 'versionNumber' => 1]);
        $backend = StoredFileStorageBackend::query()->firstOrCreate(['backendKey' => 'local-private'], ['state' => 'ACTIVE']);
        $storageKey = 'objects/test/'.str()->uuid().'.blob';
        StoredFileStorageObject::query()->create(['idFileContent' => $content->id, 'idStorageBackend' => $backend->id, 'storedSha256' => $content->logicalSha256, 'storageKey' => $storageKey, 'encoding' => 'IDENTITY', 'storedSizeBytes' => $content->logicalSizeBytes, 'state' => 'ACTIVE']);
        if ($writeBytes) {
            Storage::disk('file-private')->put($storageKey, $contents);
        }

        return StoredFilePossession::query()->create([
            'idFile' => $file->id,
            'idCurrentFileVersion' => $version->id,
            'idUser' => $tenant === null ? $folder?->idUser : null,
            'idTenant' => $tenant?->id,
            'idWorkspaceFolder' => $folder?->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => $name,
            'state' => $state,
            'logicalSizeBytes' => $content->logicalSizeBytes,
        ]);
    }
}
