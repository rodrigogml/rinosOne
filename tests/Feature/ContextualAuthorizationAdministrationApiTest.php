<?php

namespace Tests\Feature;

use App\Models\AuthorizationRole;
use App\Models\AuthorizationGroup;
use App\Models\AuthorizationRoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextualAuthorizationAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_contextual_reads_are_route_bound_and_keep_the_legacy_endpoints_independent(): void
    {
        [$administrator, $tenant] = $this->tenantAdministrator();
        $member = User::factory()->create(['displayName' => 'Membro']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);

        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/context")
            ->assertOk()
            ->assertJsonPath('context.scope', 'TENANT')
            ->assertJsonPath('context.tenantId', $tenant->id)
            ->assertJsonPath('context.workspaceKind', 'TENANT')
            ->assertJsonPath('context.capabilities.canReadAccess', true)
            ->assertJsonFragment(['advanced'])
            ->assertJsonMissingPath('apiKey')
            ->assertJsonMissingPath('secretHash')
            ->assertJsonMissingPath('credential');
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/subjects?perPage=1")
            ->assertOk()
            ->assertJsonPath('pagination.perPage', 1)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonStructure(['subjects' => [['subjectId', 'subjectType', 'displayName', 'accessSources', 'effectiveCapabilities', 'expiresAt']], 'pagination' => ['page', 'perPage', 'total', 'lastPage']]);
        AuthorizationGroup::query()->create(['idTenant' => $tenant->id, 'displayName' => 'Financeiro', 'scope' => 'TENANT', 'active' => true]);
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/groups/contextual")
            ->assertOk()
            ->assertJsonPath('groups.0.catalogType', 'GROUP')
            ->assertJsonPath('groups.0.displayName', 'Financeiro');
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/subjects/USER/{$member->id}/effective-access")
            ->assertOk()
            ->assertJsonPath('effectiveAccess.subject.subjectType', 'USER');
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/subjects/USER/999999/effective-access")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_NOT_AVAILABLE');
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/subjects?perPage=51")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->actingAs($administrator)->getJson('/api/v1/tenants/999999/authorization/context')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_DENIED');
        $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/effective-access/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('effectiveAccess.membership.state', 'ACTIVE');
    }

    public function test_personal_context_is_available_only_to_its_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/authorization/personal/context')
            ->assertOk()
            ->assertJsonPath('context.scope', 'PERSONAL')
            ->assertJsonPath('context.tenantId', null)
            ->assertJsonPath('context.workspaceKind', 'PERSONAL')
            ->assertJsonMissingPath('advanced');
        $this->actingAs($user)->getJson('/api/v1/authorization/personal/subjects')
            ->assertOk()
            ->assertJsonPath('subjects.0.subjectId', $user->id);
    }

    public function test_platform_context_requires_a_platform_administration_grant(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/platform/authorization/context')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_DENIED');

        $role = AuthorizationRole::query()->where('key', 'platform.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $user->id, 'idTenant' => null, 'state' => 'ACTIVE']);
        $this->actingAs($user)->getJson('/api/v1/platform/authorization/context')
            ->assertOk()
            ->assertJsonPath('context.scope', 'PLATFORM')
            ->assertJsonPath('context.workspaceKind', null);
    }

    public function test_contextual_user_assignment_preserves_the_legacy_payload_and_rejects_a_stale_context(): void
    {
        [$administrator, $tenant] = $this->tenantAdministrator();
        $member = User::factory()->create();
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $member->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->create(['idTenant' => $tenant->id, 'key' => 'tenant.billing.reader', 'displayName' => 'Leitor financeiro', 'description' => '', 'scope' => 'TENANT', 'type' => 'CUSTOM', 'systemManaged' => false, 'active' => true]);
        $version = $this->actingAs($administrator)->getJson("/api/v1/tenants/{$tenant->id}/authorization/context")->json('contextVersion');

        $this->actingAs($administrator)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/assignments", ['subjectType' => 'USER', 'subjectId' => $member->id, 'expectedContextVersion' => $version])
            ->assertCreated()
            ->assertJsonPath('assignment.userId', $member->id)
            ->assertJsonPath('contextVersion', fn (string $received): bool => $received !== '');
        $this->actingAs($administrator)->postJson("/api/v1/tenants/{$tenant->id}/authorization/roles/{$role->id}/assignments", ['subjectType' => 'USER', 'subjectId' => $member->id, 'expectedContextVersion' => '0'])
            ->assertStatus(412)
            ->assertJsonPath('error.code', 'AUTHORIZATION_ADMINISTRATION_CONTEXT_STALE');
    }

    /** @return array{0: User, 1: Tenant} */
    private function tenantAdministrator(): array
    {
        $administrator = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Empresa', 'state' => 'ACTIVE']);
        TenantMembership::query()->create(['idTenant' => $tenant->id, 'idUser' => $administrator->id, 'state' => 'ACTIVE']);
        $role = AuthorizationRole::query()->where('key', 'tenant.administrator')->firstOrFail();
        AuthorizationRoleAssignment::query()->create(['idRole' => $role->id, 'idUser' => $administrator->id, 'idTenant' => $tenant->id, 'state' => 'ACTIVE']);

        return [$administrator, $tenant];
    }
}
