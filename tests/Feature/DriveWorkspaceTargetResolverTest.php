<?php

namespace Tests\Feature;

use App\Contracts\FileStorage\V1\FileStorageOwnerType;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Domain\FileStorage\Exception\DriveWorkspaceTargetException;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\FileStorage\WorkspaceFolder;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Resource\AuthorizationResourceRelationService;
use App\Services\FileStorage\Drive\DriveWorkspaceTargetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveWorkspaceTargetResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_derives_the_personal_workspace_from_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $target = app(DriveWorkspaceTargetResolver::class)->personal($user);

        $this->assertSame(FileStorageOwnerType::User, $target->ownerType);
        $this->assertSame($user->id, $target->ownerId);
        $this->assertSame('personal.folder', $target->resourceType);
        $this->assertNull($target->tenantId);
    }

    public function test_it_resolves_work_only_for_an_active_membership_in_an_active_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $folder = WorkspaceFolder::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Documents', 'state' => 'ACTIVE']);
        app(AuthorizationResourceRelationService::class)->create(new ResourceReference('tenant.folder', $folder->id, AuthorizationScope::Tenant, $tenant->id), 'READ', $user);

        $target = app(DriveWorkspaceTargetResolver::class)->work($user, $tenant->id);

        $this->assertSame(FileStorageOwnerType::Tenant, $target->ownerType);
        $this->assertSame($tenant->id, $target->ownerId);
        $this->assertSame($tenant->id, $target->tenantId);
        $this->assertSame('tenant.folder', $target->resourceType);
    }

    public function test_it_never_resolves_an_unavailable_work_target(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Unavailable', 'state' => TenantState::Inactive]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);

        $this->expectException(DriveWorkspaceTargetException::class);
        app(DriveWorkspaceTargetResolver::class)->work($user, $tenant->id);
    }

    public function test_it_rejects_an_active_membership_without_effective_drive_access(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'No drive access', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);

        $this->expectException(DriveWorkspaceTargetException::class);
        app(DriveWorkspaceTargetResolver::class)->work($user, $tenant->id);
    }
}
