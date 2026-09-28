<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\Advanced\AuthorizationDelegationService;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuthorizationDelegationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_an_audited_bounded_tenant_delegation(): void
    {
        $delegator = User::factory()->create(); $recipient = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Delegation', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $recipient->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->create(['key' => 'tenant.delegate.read', 'displayName' => 'Read', 'description' => 'Read.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $role = AuthorizationRole::query()->create(['key' => 'tenant.delegate.role', 'displayName' => 'Role', 'description' => 'Role.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        DB::table('auth_role_permission')->insert(['idRole' => $role->id, 'idPermission' => $permission->id]);
        $origin = AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $delegator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $delegation = app(AuthorizationDelegationService::class)->create($delegator, $recipient, $permission, AuthorizationScope::Tenant, $tenant->id, 'ROLE_ASSIGNMENT', $origin->id, now(), now()->addDay(), ['amountMaximum' => 100]);
        $this->assertDatabaseHas('auth_delegation', ['id' => $delegation->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.delegation.created', 'targetId' => $delegation->id]);
        $this->assertTrue(app(AuthorizationService::class)->check($recipient, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        app(AuthorizationDelegationService::class)->revoke($delegation);
        $this->assertFalse(app(AuthorizationService::class)->check($recipient, $permission->key, AuthorizationScope::Tenant, $tenant->id)->allowed);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.delegation.revoked', 'targetId' => $delegation->id]);
    }

    public function test_it_rejects_self_delegation_and_validity_beyond_the_configured_limit(): void
    {
        $user = User::factory()->create(); $permission = AuthorizationPermission::query()->create(['key' => 'personal.delegate.read', 'displayName' => 'Read', 'description' => 'Read.', 'scope' => 'PERSONAL', 'systemManaged' => false, 'active' => true]);
        $this->expectException(LogicException::class);
        app(AuthorizationDelegationService::class)->create($user, $user, $permission, AuthorizationScope::Personal, null, 'ROLE_ASSIGNMENT', 1, now(), now()->addDays(31));
    }

    public function test_it_rejects_a_tenant_delegation_for_a_recipient_without_membership(): void
    {
        $delegator = User::factory()->create(); $recipient = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'No member', 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->create(['key' => 'tenant.delegate.invalid', 'displayName' => 'Read', 'description' => 'Read.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $role = AuthorizationRole::query()->create(['key' => 'tenant.delegate.invalid.role', 'displayName' => 'Role', 'description' => 'Role.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        DB::table('auth_role_permission')->insert(['idRole' => $role->id, 'idPermission' => $permission->id]);
        $origin = AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $delegator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        $this->expectException(LogicException::class);
        app(AuthorizationDelegationService::class)->create($delegator, $recipient, $permission, AuthorizationScope::Tenant, $tenant->id, 'ROLE_ASSIGNMENT', $origin->id, now(), now()->addDay());
    }
}
