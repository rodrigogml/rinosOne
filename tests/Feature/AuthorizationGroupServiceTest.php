<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuthorizationGroupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_tenant_group_with_its_tenant_context(): void
    {
        $tenant = $this->activeTenant('Acme');

        $group = app(AuthorizationGroupService::class)->create('Finance', AuthorizationScope::Tenant, $tenant->id);

        $this->assertSame($tenant->id, $group->idTenant);
        $this->assertSame(AuthorizationScope::Tenant->value, $group->scope);
        $this->assertTrue($group->active);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.group.created', 'idTenant' => $tenant->id]);
    }

    public function test_it_rejects_a_group_with_an_incompatible_or_inactive_tenant_context(): void
    {
        $tenant = $this->activeTenant('Acme');
        $inactiveTenant = Tenant::query()->create(['displayName' => 'Dormant', 'state' => TenantState::Inactive]);
        $service = app(AuthorizationGroupService::class);

        try {
            $service->create('Personal with tenant', AuthorizationScope::Personal, $tenant->id);
            $this->fail('Expected a personal group with a tenant context to be rejected.');
        } catch (LogicException) {
            // Expected: only tenant groups can use a tenant context.
        }

        $this->expectException(LogicException::class);
        $service->create('Inactive tenant group', AuthorizationScope::Tenant, $inactiveTenant->id);
    }

    public function test_it_only_adds_a_user_with_membership_in_the_same_tenant_group(): void
    {
        $firstTenant = $this->activeTenant('Acme');
        $secondTenant = $this->activeTenant('Globex');
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $firstTenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $firstGroup = app(AuthorizationGroupService::class)->create('Finance', AuthorizationScope::Tenant, $firstTenant->id);
        $secondGroup = app(AuthorizationGroupService::class)->create('Finance', AuthorizationScope::Tenant, $secondTenant->id);

        app(AuthorizationGroupService::class)->addUser($firstGroup, $user);

        $this->assertTrue(DB::table('auth_group_user')->where('idGroup', $firstGroup->id)->where('idUser', $user->id)->exists());
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.group_user.added', 'idTenant' => $firstTenant->id]);

        $this->expectException(LogicException::class);
        app(AuthorizationGroupService::class)->addUser($secondGroup, $user);
    }

    public function test_it_deactivates_groups_and_removes_their_members_without_reactivating_them(): void
    {
        $tenant = $this->activeTenant('Acme');
        $user = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => TenantMembershipState::Active]);
        $service = app(AuthorizationGroupService::class);
        $group = $service->create('Finance', AuthorizationScope::Tenant, $tenant->id);
        $service->addUser($group, $user);
        $service->deactivate($group);

        $this->assertFalse($group->fresh()->active);

        $service->removeUser($group, $user);

        $this->assertFalse(DB::table('auth_group_user')->where('idGroup', $group->id)->where('idUser', $user->id)->exists());
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.group.deactivated', 'idTenant' => $tenant->id]);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.group_user.removed', 'idTenant' => $tenant->id]);

        $this->expectException(LogicException::class);
        $service->addUser($group->fresh(), $user);
    }

    private function activeTenant(string $displayName): Tenant
    {
        return Tenant::query()->create(['displayName' => $displayName, 'state' => TenantState::Active]);
    }
}
