<?php

namespace Tests\Feature;

use App\Models\AuthorizationPermission;
use App\Models\AuthorizationRole;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdvancedAuthorizationAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_a_service_identity_grants_its_permission_and_issues_a_one_time_key(): void
    {
        [$admin, $tenant] = $this->administrator();
        $permission = AuthorizationPermission::query()->create(['key' => 'tenant.integration.export', 'displayName' => 'Export', 'description' => 'Export.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $identity = $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/service-identities", ['ownerUserId' => $admin->id, 'displayName' => 'Exporter', 'purpose' => 'Exports reports.'])
            ->assertCreated()->assertJsonPath('serviceIdentity.displayName', 'Exporter')->json('serviceIdentity');
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/service-identities/{$identity['id']}/permissions", ['permissionId' => $permission->id])->assertNoContent();
        $issued = $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/service-identities/{$identity['id']}/credentials", ['displayName' => 'Production export', 'permissionKeys' => [$permission->key]])
            ->assertCreated()->json();
        $this->assertArrayHasKey('apiKey', $issued);
        $this->assertDatabaseHas('auth_audit_event', ['operation' => 'authorization.service_credential.issued']);
    }

    public function test_administrator_publishes_binds_and_revokes_advanced_tenant_controls(): void
    {
        [$admin, $tenant] = $this->administrator();
        $recipient = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $recipient->id, 'state' => 'ACTIVE']);
        $first = AuthorizationPermission::query()->create(['key' => 'tenant.control.first', 'displayName' => 'First', 'description' => 'First.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $second = AuthorizationPermission::query()->create(['key' => 'tenant.control.second', 'displayName' => 'Second', 'description' => 'Second.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);

        $policy = $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/policies", ['key' => 'tenant.control.limit', 'definition' => ['all' => [['type' => 'AMOUNT_MAXIMUM', 'maximum' => 100]]]])
            ->assertCreated()->json('policy');
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/policies/{$policy['id']}/bindings", ['permissionId' => $first->id])->assertCreated();
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/separation-rules", ['permissionId' => $first->id, 'incompatiblePermissionId' => $second->id])->assertCreated();

        $role = AuthorizationRole::query()->create(['key' => 'tenant.control.delegator', 'displayName' => 'Delegator', 'description' => 'Delegator.', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        DB::table('auth_role_permission')->insert(['idRole' => $role->id, 'idPermission' => $first->id]);
        $origin = AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $admin->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);
        $delegation = $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/delegations", ['delegatorUserId' => $admin->id, 'recipientUserId' => $recipient->id, 'permissionId' => $first->id, 'originAssignmentId' => $origin->id, 'startsAt' => now()->toIso8601String(), 'endsAt' => now()->addDay()->toIso8601String()])
            ->assertCreated()->json('delegation');
        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/delegations/{$delegation['id']}/revocation")->assertOk()->assertJsonPath('delegation.state', 'REVOKED');
    }

    public function test_non_administrator_cannot_create_advanced_tenant_controls(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Protected', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $user->id, 'state' => 'ACTIVE']);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/policies", ['key' => 'tenant.forbidden', 'definition' => ['all' => [['type' => 'AMOUNT_MAXIMUM', 'maximum' => 10]]]])
            ->assertForbidden()->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_DENIED');
    }

    public function test_advanced_contract_validation_uses_the_standard_safe_envelope(): void
    {
        [$admin, $tenant] = $this->administrator();

        $this->actingAs($admin)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/access-requests", ['permissionId' => 0, 'startsAt' => now()->addHour()->toIso8601String(), 'endsAt' => now()->toIso8601String()])
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR')->assertJsonStructure(['fields' => ['permissionId', 'endsAt']]);
    }

    public function test_members_only_see_their_own_temporary_access_requests_while_administrator_sees_the_queue(): void
    {
        [$admin, $tenant] = $this->administrator();
        $member = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $permission = AuthorizationPermission::query()->create(['key' => 'tenant.queue.read', 'displayName' => 'Queue', 'description' => 'Queue.', 'scope' => 'TENANT', 'systemManaged' => false, 'active' => true]);
        $this->actingAs($member)->postJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/access-requests", ['permissionId' => $permission->id, 'startsAt' => now()->toIso8601String(), 'endsAt' => now()->addHour()->toIso8601String()])->assertCreated();

        $memberRequests = $this->actingAs($member)->getJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/access-requests")->assertOk()->json('accessRequests');
        $this->assertCount(1, $memberRequests);
        $adminRequests = $this->actingAs($admin)->getJson("/api/v1/tenants/{$tenant->id}/authorization/advanced/access-requests")->assertOk()->json('accessRequests');
        $this->assertCount(1, $adminRequests);
        $this->assertSame($member->id, $adminRequests[0]['requesterUserId']);
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
