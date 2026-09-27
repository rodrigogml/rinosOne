<?php

namespace Tests\Feature;

use App\Models\AuthorizationAuditEvent;
use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRestriction;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_a_tenant_role_through_the_json_contract(): void
    {
        [$admin, $tenant] = $this->administrator();
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles", ['key' => 'tenant.billing.viewer', 'displayName' => 'Billing viewer', 'description' => 'Views billing.'])
            ->assertCreated()->assertJsonPath('role.key', 'tenant.billing.viewer')->assertJsonPath('role.displayName', 'Billing viewer');
    }

    public function test_role_creation_uses_validation_and_safe_denial_envelopes(): void
    {
        [$admin, $tenant] = $this->administrator();
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles", ['key' => ''])
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->actingAs(User::factory()->create())->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles", ['key' => 'tenant.billing.viewer', 'displayName' => 'Billing viewer'])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_DENIED');
    }

    public function test_administrator_grants_a_permission_and_assigns_the_role_to_an_active_member(): void
    {
        [$admin, $tenant] = $this->administrator();
        $member = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->create(['idTenant' => $tenant->id, 'key' => 'tenant.billing.viewer', 'displayName' => 'Billing viewer', 'description' => 'Views billing.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();

        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/permissions", ['permissionId' => $permission->id])->assertNoContent();
        $assignment = $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/assignments", ['userId' => $member->id])
            ->assertCreated()->assertJsonPath('assignment.roleId', $role->id)->assertJsonPath('assignment.userId', $member->id);
        $this->actingAs($admin)->deleteJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/assignments/{$member->id}")->assertNoContent();
        $this->assertDatabaseMissing('auth_role_assignment', ['id' => $assignment->json('assignment.id')]);
    }

    public function test_missing_administrative_role_is_not_exposed_as_an_internal_error(): void
    {
        [$admin, $tenant] = $this->administrator();
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/999999/permissions", ['permissionId' => 999999])
            ->assertNotFound()->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_NOT_AVAILABLE');
    }

    public function test_unauthorized_caller_cannot_enumerate_roles_or_groups_in_another_tenant(): void
    {
        [, $tenant] = $this->administrator();
        $role = AuthorizationRole::query()->create(['idTenant' => $tenant->id, 'key' => 'tenant.billing.viewer', 'displayName' => 'Billing viewer', 'description' => 'Views billing.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);

        $this->actingAs(User::factory()->create())->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/permissions", ['permissionId' => 999999])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_DENIED');
        $this->actingAs(User::factory()->create())->postJson("/api/v1/tenants/{$tenant->id}/authorization/groups/999999/members", ['userId' => 999999])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_DENIED');
    }

    public function test_administrator_creates_a_group_adds_a_member_and_grants_a_role(): void
    {
        [$admin, $tenant] = $this->administrator();
        $member = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->create(['idTenant' => $tenant->id, 'key' => 'tenant.billing.viewer', 'displayName' => 'Billing viewer', 'description' => 'Views billing.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);

        $group = $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/groups", ['displayName' => 'Billing'])
            ->assertCreated()->assertJsonPath('group.displayName', 'Billing')->json('group');
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/groups/{$group['id']}/members", ['userId' => $member->id])->assertNoContent();
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/groups/{$group['id']}/roles", ['roleId' => $role->id])->assertNoContent();
        $this->actingAs($admin)->deleteJson("/api/v1/tenants/{$tenant->id}/authorization/groups/{$group['id']}/members/{$member->id}")->assertNoContent();
        $this->actingAs($admin)->deleteJson("/api/v1/tenants/{$tenant->id}/authorization/groups/{$group['id']}/roles/{$role->id}")->assertNoContent();
    }

    public function test_administrator_creates_and_deactivates_a_user_restriction(): void
    {
        [$admin, $tenant] = $this->administrator();
        $member = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();

        $restriction = $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/restrictions", ['permissionId' => $permission->id, 'userId' => $member->id])
            ->assertCreated()->assertJsonPath('restriction.userId', $member->id)->json('restriction');
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/restrictions/{$restriction['id']}/deactivation")->assertNoContent();
    }

    public function test_membership_removal_preserves_the_last_direct_administrator(): void
    {
        [$admin, $tenant] = $this->administrator();
        $member = User::factory()->create();
        $membership = TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $adminMembership = TenantMembership::query()->where('idTenant', $tenant->id)->where('idUser', $admin->id)->firstOrFail();

        $this->actingAs($admin)->deleteJson("/api/v1/tenants/{$tenant->id}/authorization/memberships/{$membership->id}")->assertNoContent();
        $this->assertDatabaseMissing('tenantMembership', ['id' => $membership->id]);
        $this->actingAs($admin)->deleteJson("/api/v1/tenants/{$tenant->id}/authorization/memberships/{$adminMembership->id}")
            ->assertStatus(409)->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_CONFLICT')->assertJsonPath('error.message', 'A operação não pôde ser concluída no estado atual.');
        $this->assertDatabaseHas('tenantMembership', ['id' => $adminMembership->id, 'state' => 'ACTIVE']);
        $this->assertDatabaseHas('auth_audit_event', ['idTenant' => $tenant->id, 'operation' => 'authorization.administration.rejected', 'targetId' => $adminMembership->id]);
    }

    public function test_administrator_reads_effective_access_and_explains_allow_or_restriction_deny(): void
    {
        [$admin, $tenant] = $this->administrator();
        $member = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $member->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.authorization.read')->firstOrFail();

        $this->actingAs($admin)->getJson("/api/v1/tenants/{$tenant->id}/authorization/effective-access/users/{$member->id}")
            ->assertOk()->assertJsonPath('effectiveAccess.membership.state', 'ACTIVE')->assertJsonPath('effectiveAccess.directRoles.0.id', $role->id);
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/explain/users/{$member->id}", ['permissionKey' => 'tenant.authorization.read'])
            ->assertOk()->assertJsonPath('explanation.allowed', true)->assertJsonPath('explanation.reasonCode', 'GRANT_APPLIES');
        AuthorizationRestriction::query()->create(['idPermission' => $permission->id, 'idUser' => $member->id, 'idTenant' => $tenant->id, 'scope' => 'TENANT', 'active' => true]);
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/explain/users/{$member->id}", ['permissionKey' => 'tenant.authorization.read'])
            ->assertOk()->assertJsonPath('explanation.allowed', false)->assertJsonPath('explanation.reasonCode', 'RESTRICTION_APPLIES');
    }

    public function test_effective_access_does_not_reveal_a_subject_outside_the_authorized_tenant(): void
    {
        [$admin, $tenant] = $this->administrator();
        $outsideMember = User::factory()->create();

        $this->actingAs($admin)->getJson("/api/v1/tenants/{$tenant->id}/authorization/effective-access/users/{$outsideMember->id}")
            ->assertNotFound()->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_NOT_AVAILABLE');
    }

    public function test_administrator_reads_a_paginated_tenant_scoped_redacted_audit_log(): void
    {
        [$admin, $tenant] = $this->administrator();
        $otherTenant = Tenant::query()->create(['displayName' => 'Other', 'state' => 'ACTIVE']);
        AuthorizationAuditEvent::query()->create(['occurredAt' => now(), 'idActorUser' => $admin->id, 'idTenant' => $tenant->id, 'operation' => 'authorization.test.changed', 'targetType' => 'authorization.test', 'targetId' => 10, 'before' => ['secret' => 'not returned'], 'after' => ['state' => 'ACTIVE']]);
        AuthorizationAuditEvent::query()->create(['occurredAt' => now(), 'idActorUser' => $admin->id, 'idTenant' => $otherTenant->id, 'operation' => 'authorization.test.changed', 'targetType' => 'authorization.test', 'targetId' => 20]);

        $response = $this->actingAs($admin)->getJson("/api/v1/tenants/{$tenant->id}/authorization/audit-events?operation=authorization.test.changed&perPage=1")
            ->assertOk()->assertJsonPath('pagination.perPage', 1)->assertJsonPath('pagination.total', 1)->assertJsonPath('events.0.targetId', 10);
        $this->assertArrayNotHasKey('before', $response->json('events.0'));
        $this->actingAs($admin)->getJson("/api/v1/tenants/{$tenant->id}/authorization/audit-events?perPage=51")
            ->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_revoked_role_is_recomputed_by_the_next_protected_operation_and_capability_projection(): void
    {
        [$admin, $tenant] = $this->administrator();
        $member = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();
        $role = AuthorizationRole::query()->create(['idTenant' => $tenant->id, 'key' => 'tenant.availability.operator', 'displayName' => 'Availability operator', 'description' => '', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/permissions", ['permissionId' => $permission->id])->assertNoContent();
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/assignments", ['userId' => $member->id])->assertCreated();

        $this->actingAs($member)->getJson('/api/v1/tenants')->assertOk()->assertJsonPath('tenants.0.canManageAvailability', true);
        $this->actingAs($member)->postJson("/api/v1/tenants/{$tenant->id}/availability", ['state' => 'ACTIVE'])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/assignments/{$member->id}")->assertNoContent();
        $this->actingAs($member)->getJson('/api/v1/tenants')->assertOk()->assertJsonPath('tenants.0.canManageAvailability', false);
        $this->actingAs($member)->postJson("/api/v1/tenants/{$tenant->id}/availability", ['state' => 'ACTIVE'])->assertForbidden()->assertJsonPath('error.code', 'TENANT_ADMINISTRATOR_REQUIRED');
    }

    private function administrator(): array
    {
        $admin = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Target', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $admin->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $admin->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        return [$admin, $tenant];
    }
}
