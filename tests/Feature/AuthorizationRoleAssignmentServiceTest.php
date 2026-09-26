<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationRoleType;
use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\AuthorizationRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationRoleAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationRoleAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_an_active_tenant_role_to_an_active_member(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();

        app(AuthorizationRoleAssignmentService::class)->assignTenantRole($role, $user, $tenant->id);

        $this->assertDatabaseHas('auth_role_assignment', ['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
    }

    public function test_it_rejects_a_user_without_active_membership(): void
    {
        $user = User::factory()->create();
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();

        $this->expectException(\LogicException::class);
        app(AuthorizationRoleAssignmentService::class)->assignTenantRole($role, $user, 1);
    }

    public function test_it_rejects_a_tenant_owned_role_outside_its_tenant(): void
    {
        $user = User::factory()->create();
        $ownerTenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        $otherTenant = Tenant::query()->create(['displayName' => 'Globex', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $ownerTenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        TenantMembership::query()->create(['idTenant' => $otherTenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $role = AuthorizationRole::query()->create([
            'idTenant' => $ownerTenant->id,
            'key' => 'tenant.acme.invoice.manager',
            'displayName' => 'Invoice manager',
            'description' => 'Tenant-owned custom role.',
            'scope' => AuthorizationScope::Tenant->value,
            'type' => AuthorizationRoleType::Custom->value,
            'systemManaged' => false,
            'active' => true,
        ]);

        $this->expectException(\LogicException::class);

        app(AuthorizationRoleAssignmentService::class)->assignTenantRole($role, $user, $otherTenant->id);
    }

    public function test_it_rejects_removing_or_deactivating_the_last_tenant_administrator(): void
    {
        [$user, $tenant, $role] = $this->administratorContext();
        $service = app(AuthorizationRoleAssignmentService::class);

        try {
            $service->deactivate($role, $user, $tenant->id);
            $this->fail('Expected the last administrator invariant to reject deactivation.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('auth_role_assignment', ['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        }

        $this->expectException(\LogicException::class);
        $service->remove($role, $user, $tenant->id);
    }

    public function test_it_transfers_administration_before_deactivating_the_previous_administrator(): void
    {
        [$from, $tenant, $role] = $this->administratorContext();
        $to = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $to->id, 'state' => TenantMembershipState::Active]);

        app(AuthorizationRoleAssignmentService::class)->transferTenantAdministrator($from, $to, $tenant->id);

        $this->assertDatabaseHas('auth_role_assignment', ['idRole' => $role->id, 'idUser' => $from->id, 'idTenant' => $tenant->id, 'state' => 'INACTIVE']);
        $this->assertDatabaseHas('auth_role_assignment', ['idRole' => $role->id, 'idUser' => $to->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
    }

    private function administratorContext(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        app(AuthorizationRoleAssignmentService::class)->assignTenantAdministrator($user, $tenant->id);

        return [$user, $tenant, $role];
    }
}
