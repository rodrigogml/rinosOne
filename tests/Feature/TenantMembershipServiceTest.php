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
use App\Services\Authorization\AuthorizationRoleAssignmentService;
use App\Services\Authorization\AuthorizationRolePermissionService;
use App\Services\Authorization\AuthorizationService;
use App\Services\Tenant\TenantMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_revokes_direct_permissions_on_deactivation_and_restores_them_on_activation(): void
    {
        [$tenant, $user, $membership, $role, $permission] = $this->memberWithDirectPermission();
        $authorization = app(AuthorizationService::class);
        $service = app(TenantMembershipService::class);

        $this->assertTrue($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);

        $service->deactivate($membership, $user->id, 'membership-deactivation');

        $this->assertFalse($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertDatabaseHas('auth_audit_event', [
            'operation' => 'authorization.tenant_membership.deactivated',
            'targetType' => 'tenant.membership',
            'targetId' => $membership->id,
            'idTenant' => $tenant->id,
        ]);

        $service->activate($membership, $user->id, 'membership-activation');

        $this->assertTrue($authorization->check($user, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertDatabaseHas('auth_audit_event', [
            'operation' => 'authorization.tenant_membership.activated',
            'targetType' => 'tenant.membership',
            'targetId' => $membership->id,
            'idTenant' => $tenant->id,
        ]);
    }

    public function test_it_rejects_deactivation_or_removal_of_the_last_active_direct_administrator(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        $user = User::factory()->create();
        $membership = TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'state' => TenantMembershipState::Active,
        ]);
        app(AuthorizationRoleAssignmentService::class)->assignTenantAdministrator($user, $tenant->id);
        $service = app(TenantMembershipService::class);

        try {
            $service->deactivate($membership);
            $this->fail('Expected the last administrator invariant to reject membership deactivation.');
        } catch (LogicException) {
            $this->assertDatabaseHas('tenantMembership', ['id' => $membership->id, 'state' => TenantMembershipState::Active->value]);
        }

        $this->expectException(LogicException::class);
        $service->remove($membership);
    }

    public function test_it_removes_a_non_administrator_membership_and_records_an_audit_event(): void
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        $user = User::factory()->create();
        $membership = TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'state' => TenantMembershipState::Active,
        ]);

        app(TenantMembershipService::class)->remove($membership, $user->id, 'membership-removal');

        $this->assertDatabaseMissing('tenantMembership', ['id' => $membership->id]);
        $this->assertDatabaseHas('auth_audit_event', [
            'operation' => 'authorization.tenant_membership.removed',
            'targetType' => 'tenant.membership',
            'targetId' => $membership->id,
            'idTenant' => $tenant->id,
        ]);
    }

    private function memberWithDirectPermission(): array
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        $user = User::factory()->create();
        $membership = TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'state' => TenantMembershipState::Active,
        ]);
        $role = AuthorizationRole::query()->create([
            'idTenant' => $tenant->id,
            'key' => 'tenant.acme.report.reader',
            'displayName' => 'Report reader',
            'description' => 'Tenant-owned custom role.',
            'scope' => AuthorizationScope::Tenant->value,
            'type' => AuthorizationRoleType::Custom->value,
            'systemManaged' => false,
            'active' => true,
        ]);
        $permission = AuthorizationPermission::query()->create([
            'key' => 'tenant.report.read',
            'displayName' => 'Read reports',
            'description' => 'Reads tenant reports.',
            'scope' => AuthorizationScope::Tenant->value,
            'systemManaged' => false,
            'active' => true,
        ]);
        app(AuthorizationRolePermissionService::class)->grant($role, $permission);
        app(AuthorizationRoleAssignmentService::class)->assignTenantRole($role, $user, $tenant->id);

        return [$tenant, $user, $membership, $role, $permission];
    }
}
