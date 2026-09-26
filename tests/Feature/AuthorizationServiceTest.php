<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationGroupService;
use App\Services\Authorization\AuthorizationRestrictionService;
use App\Services\Authorization\AuthorizationRoleAssignmentService;
use App\Services\Authorization\AuthorizationRolePermissionService;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_a_tenant_grant_only_in_its_active_tenant(): void
    {
        [$user, $tenant, $role, $permission] = $this->tenantGrant();

        $decision = app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id);

        $this->assertTrue($decision->allowed);
        $this->assertSame('GRANT_APPLIES', $decision->reasonCode);
    }

    public function test_it_denies_a_tenant_grant_in_another_tenant(): void
    {
        [$user, , , $permission] = $this->tenantGrant();
        $other = Tenant::query()->create(['displayName' => 'Other', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $other->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);

        $decision = app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $other->id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('NO_APPLICABLE_GRANT', $decision->reasonCode);
    }

    public function test_it_denies_tenant_context_for_a_personal_decision(): void
    {
        $decision = app(AuthorizationService::class)->check(User::factory()->create(), 'personal.file.read', AuthorizationScope::Personal, 1);

        $this->assertFalse($decision->allowed);
        $this->assertSame('TENANT_CONTEXT_NOT_ALLOWED', $decision->reasonCode);
    }

    public function test_it_allows_a_platform_grant_without_tenant_context_and_defaults_to_deny_for_personal(): void
    {
        $user = User::factory()->create();
        $role = AuthorizationRole::query()->create(['key' => 'platform.catalog.manager', 'displayName' => 'Catalog manager', 'description' => 'Manages catalog.', 'scope' => 'PLATFORM', 'type' => 'SYSTEM', 'systemManaged' => true, 'active' => true]);
        $permission = AuthorizationPermission::query()->create(['key' => 'platform.catalog.manage', 'displayName' => 'Manage catalog', 'description' => 'Manages catalog.', 'scope' => 'PLATFORM', 'systemManaged' => true, 'active' => true]);
        app(AuthorizationRolePermissionService::class)->grant($role, $permission);
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => null, 'state' => 'ACTIVE']);

        $platform = app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Platform);
        $personal = app(AuthorizationService::class)->check($user, 'personal.file.read', AuthorizationScope::Personal);

        $this->assertTrue($platform->allowed);
        $this->assertFalse($personal->allowed);
        $this->assertSame('NO_APPLICABLE_GRANT', $personal->reasonCode);
    }

    public function test_an_active_direct_restriction_denies_a_tenant_administrator_permission(): void
    {
        [$user, $tenant, , $permission] = $this->tenantGrant();
        app(AuthorizationRestrictionService::class)->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id);

        $decision = app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('RESTRICTION_APPLIES', $decision->reasonCode);
    }

    public function test_an_active_group_restriction_denies_a_group_member_permission(): void
    {
        [$user, $tenant, , $permission] = $this->tenantGrant();
        $group = app(AuthorizationGroupService::class)->create('Restricted reports', AuthorizationScope::Tenant, $tenant->id);
        app(AuthorizationGroupService::class)->addUser($group, $user);
        app(AuthorizationRestrictionService::class)->create($permission, null, $group, AuthorizationScope::Tenant, $tenant->id);

        $decision = app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id);

        $this->assertFalse($decision->allowed);
        $this->assertSame('RESTRICTION_APPLIES', $decision->reasonCode);
    }

    private function tenantGrant(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();
        app(AuthorizationRolePermissionService::class)->grant($role, $permission);
        app(AuthorizationRoleAssignmentService::class)->assignTenantRole($role, $user, $tenant->id);

        return [$user, $tenant, $role, $permission];
    }
}
