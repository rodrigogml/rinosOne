<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationRoleType;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationGroupRoleAssignmentService;
use App\Services\Authorization\AuthorizationGroupService;
use App\Services\Authorization\AuthorizationRolePermissionService;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuthorizationGroupRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_unions_active_group_grants_and_revokes_them_on_the_next_decision(): void
    {
        $tenant = $this->activeTenant();
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        [$firstRole, $firstPermission] = $this->roleWithPermission('tenant.invoice.read', 'tenant.invoice.reader');
        [$secondRole, $secondPermission] = $this->roleWithPermission('tenant.invoice.manage', 'tenant.invoice.manager');
        $groups = app(AuthorizationGroupService::class);
        $firstGroup = $groups->create('Invoice readers', AuthorizationScope::Tenant, $tenant->id);
        $secondGroup = $groups->create('Invoice managers', AuthorizationScope::Tenant, $tenant->id);
        $groups->addUser($firstGroup, $user);
        $groups->addUser($secondGroup, $user);
        $assignments = app(AuthorizationGroupRoleAssignmentService::class);
        $assignments->grant($firstRole, $firstGroup);
        $assignments->grant($secondRole, $secondGroup);
        $authorization = app(AuthorizationService::class);

        $this->assertTrue($authorization->check($user, $firstPermission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertTrue($authorization->check($user, $secondPermission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $groups->removeUser($firstGroup, $user);

        $this->assertFalse($authorization->check($user, $firstPermission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertTrue($authorization->check($user, $secondPermission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $assignments->deactivate($secondRole, $secondGroup);

        $this->assertFalse($authorization->check($user, $secondPermission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.group_role_assignment.deactivated', 'idTenant' => $tenant->id]);

        $assignments->grant($secondRole, $secondGroup);

        $this->assertTrue($authorization->check($user, $secondPermission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $assignments->remove($secondRole, $secondGroup);

        $this->assertFalse($authorization->check($user, $secondPermission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.group_role_assignment.removed', 'idTenant' => $tenant->id]);
    }

    public function test_it_rejects_role_and_group_scope_mismatch(): void
    {
        $tenant = $this->activeTenant();
        $group = app(AuthorizationGroupService::class)->create('Finance', AuthorizationScope::Tenant, $tenant->id);
        $role = AuthorizationRole::query()->create([
            'key' => 'personal.file.manager',
            'displayName' => 'File manager',
            'description' => 'Manages personal files.',
            'scope' => AuthorizationScope::Personal->value,
            'type' => AuthorizationRoleType::Custom->value,
            'systemManaged' => false,
            'active' => true,
        ]);

        $this->expectException(LogicException::class);

        app(AuthorizationGroupRoleAssignmentService::class)->grant($role, $group);
    }

    public function test_a_tenant_group_grant_does_not_apply_in_another_tenant(): void
    {
        $firstTenant = $this->activeTenant('Acme');
        $secondTenant = $this->activeTenant('Globex');
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $firstTenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        TenantMembership::query()->create(['idTenant' => $secondTenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        [$role, $permission] = $this->roleWithPermission('tenant.payment.read', 'tenant.payment.reader');
        $group = app(AuthorizationGroupService::class)->create('Payment readers', AuthorizationScope::Tenant, $firstTenant->id);
        app(AuthorizationGroupService::class)->addUser($group, $user);
        app(AuthorizationGroupRoleAssignmentService::class)->grant($role, $group);

        $authorization = app(AuthorizationService::class);

        $this->assertTrue($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $firstTenant->id)->allowed);
        $this->assertFalse($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $secondTenant->id)->allowed);
    }

    public function test_it_rejects_a_tenant_owned_role_for_a_group_from_another_tenant(): void
    {
        $ownerTenant = $this->activeTenant('Acme');
        $otherTenant = $this->activeTenant('Globex');
        $role = AuthorizationRole::query()->create([
            'idTenant' => $ownerTenant->id,
            'key' => 'tenant.acme.payment.manager',
            'displayName' => 'Payment manager',
            'description' => 'Tenant-owned custom role.',
            'scope' => AuthorizationScope::Tenant->value,
            'type' => AuthorizationRoleType::Custom->value,
            'systemManaged' => false,
            'active' => true,
        ]);
        $group = app(AuthorizationGroupService::class)->create('Payment managers', AuthorizationScope::Tenant, $otherTenant->id);

        $this->expectException(LogicException::class);

        app(AuthorizationGroupRoleAssignmentService::class)->grant($role, $group);
    }

    public function test_an_inactive_group_does_not_contribute_its_role_grant(): void
    {
        $tenant = $this->activeTenant();
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        [$role, $permission] = $this->roleWithPermission('tenant.report.read', 'tenant.report.reader');
        $groups = app(AuthorizationGroupService::class);
        $group = $groups->create('Report readers', AuthorizationScope::Tenant, $tenant->id);
        $groups->addUser($group, $user);
        app(AuthorizationGroupRoleAssignmentService::class)->grant($role, $group);

        $this->assertTrue(app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $groups->deactivate($group);

        $this->assertFalse(app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
    }

    private function activeTenant(string $displayName = 'Acme'): Tenant
    {
        return Tenant::query()->create(['displayName' => $displayName, 'state' => TenantState::Active]);
    }

    private function roleWithPermission(string $permissionKey, string $roleKey): array
    {
        $role = AuthorizationRole::query()->create([
            'key' => $roleKey,
            'displayName' => $roleKey,
            'description' => 'Tenant role.',
            'scope' => AuthorizationScope::Tenant->value,
            'type' => AuthorizationRoleType::Custom->value,
            'systemManaged' => false,
            'active' => true,
        ]);
        $permission = AuthorizationPermission::query()->create([
            'key' => $permissionKey,
            'displayName' => $permissionKey,
            'description' => 'Tenant permission.',
            'scope' => AuthorizationScope::Tenant->value,
            'systemManaged' => false,
            'active' => true,
        ]);
        app(AuthorizationRolePermissionService::class)->grant($role, $permission);

        return [$role, $permission];
    }
}
