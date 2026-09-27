<?php

namespace Tests\Feature;

use App\Domain\Authorization\Administration\AuthorizationAdministrationAccessDeniedException;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Administration\AuthorizationAdministrationAuthorizer;
use App\Services\Authorization\Administration\AuthorizationAdministrationFacade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAdministrationAuthorizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_administrator_requires_membership_in_the_target_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Target', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        app(AuthorizationAdministrationAuthorizer::class)->assertCanManage($user, $tenant->id);

        $otherTenant = Tenant::query()->create(['displayName' => 'Other', 'state' => 'ACTIVE']);
        $this->expectException(AuthorizationAdministrationAccessDeniedException::class);
        app(AuthorizationAdministrationAuthorizer::class)->assertCanManage($user, $otherTenant->id);
    }

    public function test_platform_administrator_can_manage_without_membership(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Target', 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'platform.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => null, 'state' => 'ACTIVE']);

        app(AuthorizationAdministrationAuthorizer::class)->assertCanRead($user, $tenant->id);
        app(AuthorizationAdministrationAuthorizer::class)->assertCanManage($user, $tenant->id);

        $this->addToAssertionCount(2);
    }

    public function test_tenant_administrator_can_create_a_role_grant_it_and_assign_it_to_an_active_member(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Target', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $admin->id, 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $administrator = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $administrator->id, 'idUser' => $admin->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();

        $facade = app(AuthorizationAdministrationFacade::class);
        $role = $facade->createTenantRole($admin, $tenant->id, 'tenant.billing.viewer', 'Billing viewer', 'Views billing data.');
        $facade->grantPermission($admin, $tenant->id, $role, $permission);
        $assignment = $facade->assignRole($admin, $tenant->id, $role, $member);

        $this->assertSame($tenant->id, $role->idTenant);
        $this->assertSame($member->id, $assignment->idUser);
        $this->assertDatabaseHas('auth_role_permission', ['idRole' => $role->id, 'idPermission' => $permission->id]);

        $facade->removeRoleAssignment($admin, $tenant->id, $role, $member);

        $this->assertDatabaseMissing('auth_role_assignment', ['id' => $assignment->id]);
    }

    public function test_administration_can_transfer_but_not_remove_the_last_direct_tenant_administrator(): void
    {
        $admin = User::factory()->create();
        $successor = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Target', 'state' => 'ACTIVE']);
        foreach ([$admin, $successor] as $user) {
            TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);
        }
        $administrator = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        $adminAssignment = AuthorizationRoleAssignment::query()->create(['idRole' => $administrator->id, 'idUser' => $admin->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        $facade = app(AuthorizationAdministrationFacade::class);
        $facade->assignRole($admin, $tenant->id, $administrator, $successor);
        $facade->removeRoleAssignment($admin, $tenant->id, $administrator, $admin);

        $this->assertDatabaseMissing('auth_role_assignment', ['id' => $adminAssignment->id]);
        try {
            $facade->removeRoleAssignment($successor, $tenant->id, $administrator, $successor);
            $this->fail('The last direct tenant administrator must be retained.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('auth_role_assignment', ['idUser' => $successor->id, 'idRole' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        }
    }

    public function test_tenant_administrator_cannot_create_a_role_in_another_tenant(): void
    {
        $admin = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Authorized', 'state' => 'ACTIVE']);
        $otherTenant = Tenant::query()->create(['displayName' => 'Other', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $admin->id, 'state' => 'ACTIVE']);
        $administrator = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $administrator->id, 'idUser' => $admin->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        $this->expectException(AuthorizationAdministrationAccessDeniedException::class);
        app(AuthorizationAdministrationFacade::class)->createTenantRole($admin, $otherTenant->id, 'tenant.other.viewer', 'Other viewer', 'Must not be created.');
    }
}
