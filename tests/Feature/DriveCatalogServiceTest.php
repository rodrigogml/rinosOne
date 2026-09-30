<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
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
use App\Services\FileStorage\Drive\DriveCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_only_personal_admin_or_navigable_folder_workspaces_and_keeps_direct_files_virtual(): void
    {
        $principal = User::factory()->create();
        $membershipOnly = $this->tenantFor($principal, 'Membership only');
        $sharedFolderTenant = $this->tenantFor($principal, 'Shared folder');
        $sharedFileTenant = $this->tenantFor($principal, 'Shared file');
        $administratorTenant = $this->tenantFor($principal, 'Administrator');
        $folder = WorkspaceFolder::query()->create(['idTenant' => $sharedFolderTenant->id, 'displayName' => 'Contracts', 'state' => 'ACTIVE']);
        $file = $this->file($sharedFileTenant);
        $relations = app(AuthorizationResourceRelationService::class);
        $folderRelation = $relations->create(new ResourceReference('tenant.folder', $folder->id, AuthorizationScope::Tenant, $sharedFolderTenant->id), 'READ', $principal);
        $relations->create(new ResourceReference('tenant.folder', $folder->id, AuthorizationScope::Tenant, $sharedFolderTenant->id), 'READ', $principal);
        $relations->create(new ResourceReference('tenant.file', $file->id, AuthorizationScope::Tenant, $sharedFileTenant->id), 'READ', $principal);
        $administratorRole = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $administratorRole->id, 'idUser' => $principal->id, 'idTenant' => $administratorTenant->id, 'state' => 'ACTIVE']);

        $catalog = app(DriveCatalogService::class)->catalog($principal);
        $tenantIds = collect($catalog['drives'])
            ->filter(static fn (array $drive): bool => $drive['category'] === 'TENANT')
            ->pluck('target.tenantId')
            ->all();

        $this->assertSame([$administratorTenant->id, $sharedFolderTenant->id], $tenantIds);
        $this->assertSame('personal', $catalog['drives'][0]['target']['kind']);
        $this->assertSame('Compartilhados comigo', $catalog['sharedWithMe']['displayName']);
        $this->assertNotContains($membershipOnly->id, $tenantIds);
        $this->assertNotContains($sharedFileTenant->id, $tenantIds);

        $relations->deactivate($folderRelation);
        $afterRevocation = app(DriveCatalogService::class)->catalog($principal);
        $tenantIdsAfterRevocation = collect($afterRevocation['drives'])
            ->filter(static fn (array $drive): bool => $drive['category'] === 'TENANT')
            ->pluck('target.tenantId')
            ->all();

        $this->assertSame([$administratorTenant->id], $tenantIdsAfterRevocation);
    }

    private function tenantFor(User $user, string $displayName): Tenant
    {
        $tenant = Tenant::query()->create(['displayName' => $displayName, 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);

        return $tenant;
    }

    private function file(Tenant $tenant): StoredFilePossession
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
            'idTenant' => $tenant->id,
            'storageArea' => 'WORKSPACE',
            'displayName' => 'Only direct file.pdf',
            'state' => 'ACTIVE',
            'logicalSizeBytes' => 42,
        ]);
    }
}
