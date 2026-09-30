<?php

namespace Tests\Feature;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Jobs\ProvisionTenantSchema;
use App\Models\AuthorizationPermission;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Authorization\AuthorizationRestrictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_its_tenant(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $key = (string) str()->uuid();

        $created = $this->actingAs($user)->postJson('/api/v1/tenants', ['displayName' => 'Acme'], ['Idempotency-Key' => $key])
            ->assertStatus(202)
            ->assertJsonPath('tenant.displayName', 'Acme')
            ->assertJsonPath('tenant.state', 'PROVISIONING');
        $tenantId = $created->json('tenant.id');

        $this->actingAs($user)->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonPath('tenants.0.id', $tenantId)
            ->assertJsonPath('tenants.0.selectable', false)
            ->assertJsonPath('tenants.0.canManageAvailability', false);
        Queue::assertPushed(ProvisionTenantSchema::class, 1);
    }

    public function test_creation_validation_uses_the_tenant_error_envelope(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/tenants', ['displayName' => ''], ['Idempotency-Key' => 'invalid'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['fields' => ['displayName', 'idempotencyKey']]);
    }

    public function test_context_is_available_only_for_an_active_membership_and_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->activeTenantFor($user);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonPath('context.tenant.id', $tenant->id)
            ->assertJsonPath('context.capabilities.canManageAvailability', true)
            ->assertJsonPath('context.capabilities.canReadAuthorization', true)
            ->assertJsonPath('context.capabilities.canReadPeople', true)
            ->assertJsonPath('context.availableModules', []);

        $this->actingAs(User::factory()->create())->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TENANT_NOT_AVAILABLE');
    }

    public function test_tenant_payload_uses_numeric_ids_camel_case_keys_and_boolean_capabilities(): void
    {
        $user = User::factory()->create();
        $tenant = $this->activeTenantFor($user);

        $listing = $this->actingAs($user)->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonStructure(['tenants' => [['id', 'displayName', 'state', 'selectable', 'canManageAvailability', 'canReadAuthorization']]]);

        $this->assertIsInt($listing->json('tenants.0.id'));
        $this->assertIsBool($listing->json('tenants.0.selectable'));
        $this->assertIsBool($listing->json('tenants.0.canManageAvailability'));
        $this->assertIsBool($listing->json('tenants.0.canReadAuthorization'));

        $context = $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonStructure(['context' => ['tenant' => ['id', 'displayName'], 'membership' => ['id'], 'capabilities' => ['canManageAvailability', 'canReadAuthorization', 'canReadPeople', 'canCreatePeople', 'canUpdatePeople', 'canDuplicatePeople', 'canInactivatePeople', 'canReactivatePeople', 'canDeletePeople'], 'availableModules']]);

        $this->assertIsInt($context->json('context.tenant.id'));
        $this->assertIsInt($context->json('context.membership.id'));
        $this->assertIsBool($context->json('context.capabilities.canManageAvailability'));
        $this->assertIsBool($context->json('context.capabilities.canReadAuthorization'));
        $this->assertIsBool($context->json('context.capabilities.canReadPeople'));
    }

    public function test_listing_is_ordered_by_the_latest_successful_context_selection(): void
    {
        $user = User::factory()->create();
        $older = $this->activeTenantFor($user);
        $newer = $this->activeTenantFor($user);
        TenantMembership::query()->where('idTenant', $older->id)->update(['lastContextSelectedAt' => now()->subMinute()]);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$newer->id}/contexts")->assertOk();

        $this->actingAs($user)->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonPath('tenants.0.id', $newer->id)
            ->assertJsonPath('tenants.1.id', $older->id);

        $this->assertNotNull(TenantMembership::query()->where('idTenant', $newer->id)->value('lastContextSelectedAt'));
    }

    public function test_administrator_can_change_availability_and_the_tenant_stops_being_selectable(): void
    {
        $user = User::factory()->create();
        $tenant = $this->activeTenantFor($user);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/availability", ['state' => 'INACTIVE'])
            ->assertOk()
            ->assertJsonPath('tenant.state', 'INACTIVE')
            ->assertJsonPath('tenant.selectable', false);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TENANT_NOT_AVAILABLE');
    }

    public function test_active_member_without_administrator_permission_cannot_change_availability(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'state' => TenantMembershipState::Active,
        ]);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/availability", ['state' => 'INACTIVE'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TENANT_ADMINISTRATOR_REQUIRED');
    }

    public function test_removing_a_grant_denies_the_next_authenticated_request(): void
    {
        $user = User::factory()->create();
        $tenant = $this->activeTenantFor($user);

        $this->actingAs($user)->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonPath('tenants.0.canManageAvailability', true);

        DB::table('auth_role_assignment')->where('idUser', $user->id)->where('idTenant', $tenant->id)->delete();

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/availability", ['state' => 'INACTIVE'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TENANT_ADMINISTRATOR_REQUIRED');
    }

    public function test_context_capabilities_are_projected_again_after_a_grant_is_revoked(): void
    {
        $user = User::factory()->create();
        $tenant = $this->activeTenantFor($user);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonPath('context.capabilities.canManageAvailability', true);

        DB::table('auth_role_assignment')->where('idUser', $user->id)->where('idTenant', $tenant->id)->delete();

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonPath('context.capabilities.canManageAvailability', false)
            ->assertJsonPath('context.capabilities.canReadAuthorization', false)
            ->assertJsonPath('context.capabilities.canReadPeople', false);
    }

    public function test_restriction_changes_capabilities_and_the_next_protected_request_without_revealing_its_details(): void
    {
        $user = User::factory()->create();
        $tenant = $this->activeTenantFor($user);
        $permission = AuthorizationPermission::query()->where('key', 'tenant.availability.manage')->firstOrFail();
        $restrictions = app(AuthorizationRestrictionService::class);
        $restriction = $restrictions->create($permission, $user, null, AuthorizationScope::Tenant, $tenant->id);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonPath('context.capabilities.canManageAvailability', false);

        $response = $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/availability", ['state' => 'INACTIVE'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'TENANT_ADMINISTRATOR_REQUIRED');
        $this->assertSame(['code' => 'TENANT_ADMINISTRATOR_REQUIRED', 'message' => 'Ação não permitida para este tenant.'], $response->json('error'));

        $restrictions->deactivate($restriction);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonPath('context.capabilities.canManageAvailability', true);

        $restrictions->activate($restriction);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonPath('context.capabilities.canManageAvailability', false);

        $restrictions->remove($restriction);

        $this->actingAs($user)->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertOk()
            ->assertJsonPath('context.capabilities.canManageAvailability', true);
    }

    private function activeTenantFor(User $user): Tenant
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'state' => TenantMembershipState::Active,
        ]);

        $roleId = (int) DB::table('auth_role')->where('key', 'tenant.administrator')->value('id');
        DB::table('auth_role_assignment')->insert([
            'idRole' => $roleId,
            'idUser' => $user->id,
            'idTenant' => $tenant->id,
            'state' => 'ACTIVE',
        ]);

        return $tenant;
    }
}
