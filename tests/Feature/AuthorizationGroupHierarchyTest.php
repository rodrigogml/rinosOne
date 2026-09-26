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
use App\Services\Authorization\AuthorizationGroupHierarchyService;
use App\Services\Authorization\AuthorizationGroupRoleAssignmentService;
use App\Services\Authorization\AuthorizationGroupService;
use App\Services\Authorization\AuthorizationRolePermissionService;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuthorizationGroupHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_child_member_inherits_an_ancestors_grant_through_a_multi_level_chain(): void
    {
        [$tenant, $user] = $this->tenantMember();
        [$role, $permission] = $this->roleWithPermission();
        $groups = app(AuthorizationGroupService::class);
        $root = $groups->create('Finance', AuthorizationScope::Tenant, $tenant->id);
        $middle = $groups->create('Accounts payable', AuthorizationScope::Tenant, $tenant->id);
        $child = $groups->create('Invoice review', AuthorizationScope::Tenant, $tenant->id);
        $groups->addUser($child, $user);
        app(AuthorizationGroupRoleAssignmentService::class)->grant($role, $root);
        $hierarchy = app(AuthorizationGroupHierarchyService::class);
        $hierarchy->addChildGroup($root, $middle);
        $hierarchy->addChildGroup($middle, $child);

        $this->assertTrue(app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $groups->deactivate($middle);

        $this->assertFalse(app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
    }

    public function test_it_rejects_direct_and_indirect_cycles(): void
    {
        $tenant = $this->activeTenant();
        $groups = app(AuthorizationGroupService::class);
        $first = $groups->create('First', AuthorizationScope::Tenant, $tenant->id);
        $second = $groups->create('Second', AuthorizationScope::Tenant, $tenant->id);
        $third = $groups->create('Third', AuthorizationScope::Tenant, $tenant->id);
        $hierarchy = app(AuthorizationGroupHierarchyService::class);
        $hierarchy->addChildGroup($first, $second);

        try {
            $hierarchy->addChildGroup($second, $first);
            $this->fail('Expected a direct hierarchy cycle to be rejected.');
        } catch (LogicException) {
            // Expected: second already descends from first.
        }

        $hierarchy->addChildGroup($second, $third);

        $this->expectException(LogicException::class);
        $hierarchy->addChildGroup($third, $first);
    }

    public function test_removing_a_hierarchy_link_revokes_the_ancestor_grant_on_the_next_decision(): void
    {
        [$tenant, $user] = $this->tenantMember();
        [$role, $permission] = $this->roleWithPermission();
        $groups = app(AuthorizationGroupService::class);
        $parent = $groups->create('Finance', AuthorizationScope::Tenant, $tenant->id);
        $child = $groups->create('Invoices', AuthorizationScope::Tenant, $tenant->id);
        $groups->addUser($child, $user);
        app(AuthorizationGroupRoleAssignmentService::class)->grant($role, $parent);
        $hierarchy = app(AuthorizationGroupHierarchyService::class);
        $hierarchy->addChildGroup($parent, $child);

        $this->assertTrue(app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $hierarchy->removeChildGroup($parent, $child);

        $this->assertFalse(app(AuthorizationService::class)->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.group_child.removed', 'idTenant' => $tenant->id]);
    }

    public function test_it_rejects_a_hierarchy_between_groups_from_different_tenants(): void
    {
        $groups = app(AuthorizationGroupService::class);
        $parent = $groups->create('Finance', AuthorizationScope::Tenant, $this->activeTenant('Acme')->id);
        $child = $groups->create('Finance', AuthorizationScope::Tenant, $this->activeTenant('Globex')->id);

        $this->expectException(LogicException::class);

        app(AuthorizationGroupHierarchyService::class)->addChildGroup($parent, $child);
    }

    private function tenantMember(): array
    {
        $tenant = $this->activeTenant();
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);

        return [$tenant, $user];
    }

    private function activeTenant(string $displayName = 'Acme'): Tenant
    {
        return Tenant::query()->create(['displayName' => $displayName, 'state' => TenantState::Active]);
    }

    private function roleWithPermission(): array
    {
        $role = AuthorizationRole::query()->create([
            'key' => 'tenant.invoice.approver',
            'displayName' => 'Invoice approver',
            'description' => 'Approves invoices.',
            'scope' => AuthorizationScope::Tenant->value,
            'type' => AuthorizationRoleType::Custom->value,
            'systemManaged' => false,
            'active' => true,
        ]);
        $permission = AuthorizationPermission::query()->create([
            'key' => 'tenant.invoice.approve',
            'displayName' => 'Approve invoices',
            'description' => 'Approves invoices.',
            'scope' => AuthorizationScope::Tenant->value,
            'systemManaged' => false,
            'active' => true,
        ]);
        app(AuthorizationRolePermissionService::class)->grant($role, $permission);

        return [$role, $permission];
    }
}
