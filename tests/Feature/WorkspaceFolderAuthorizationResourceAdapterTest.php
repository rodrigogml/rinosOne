<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Infrastructure\FileStorage\Authorization\WorkspaceFolderAuthorizationResourceAdapter;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WorkspaceFolderAuthorizationResourceAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_only_an_active_personal_folder_and_declares_read_and_edit_contracts(): void
    {
        $user = User::factory()->create();
        $folder = WorkspaceFolder::query()->create(['idUser' => $user->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        $registry = app(AuthorizationResourceRegistry::class);

        $registry->assertAvailable(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal));
        $adapter = new WorkspaceFolderAuthorizationResourceAdapter(AuthorizationScope::Personal);

        $this->assertSame(['personal.folder.read', 'personal.folder.edit'], $adapter->supportedActions());
        $this->assertSame(['READ', 'EDIT'], $adapter->supportedRelations());

        $folder->forceFill(['state' => 'TRASHED'])->save();
        $this->expectException(LogicException::class);
        $registry->assertAvailable(new ResourceReference('personal.folder', $folder->id, AuthorizationScope::Personal));
    }

    public function test_it_confirms_the_tenant_context_and_never_treats_a_personal_folder_as_tenant_owned(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Other', 'state' => 'ACTIVE']);
        $tenantFolder = WorkspaceFolder::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Finance', 'state' => 'ACTIVE']);
        $personalFolder = WorkspaceFolder::query()->create(['idUser' => User::factory()->create()->id, 'displayName' => 'Private', 'state' => 'ACTIVE']);
        $registry = app(AuthorizationResourceRegistry::class);

        $registry->assertAvailable(new ResourceReference('tenant.folder', $tenantFolder->id, AuthorizationScope::Tenant, $tenant->id));

        foreach ([
            new ResourceReference('tenant.folder', $tenantFolder->id, AuthorizationScope::Tenant, $otherTenant->id),
            new ResourceReference('tenant.folder', $personalFolder->id, AuthorizationScope::Tenant, $tenant->id),
        ] as $reference) {
            try {
                $registry->assertAvailable($reference);
                $this->fail('An incompatible folder reference must not be available.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
