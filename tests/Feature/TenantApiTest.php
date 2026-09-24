<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantMembershipRole;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantState;
use App\Jobs\ProvisionTenantSchema;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_list_its_tenant(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $key = (string) Str::ulid();

        $created = $this->actingAs($user)->postJson('/api/v1/tenants', ['displayName' => 'Acme'], ['Idempotency-Key' => $key])
            ->assertStatus(202)
            ->assertJsonPath('tenant.displayName', 'Acme')
            ->assertJsonPath('tenant.state', 'PROVISIONING');
        $tenantId = $created->json('tenant.id');

        $this->actingAs($user)->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonPath('tenants.0.id', $tenantId)
            ->assertJsonPath('tenants.0.selectable', false)
            ->assertJsonPath('tenants.0.role', 'OWNER');
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
            ->assertJsonPath('context.availableModules', []);

        $this->actingAs(User::factory()->create())->postJson("/api/v1/tenants/{$tenant->id}/contexts")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TENANT_NOT_AVAILABLE');
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

    public function test_owner_can_change_availability_and_the_tenant_stops_being_selectable(): void
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

    private function activeTenantFor(User $user): Tenant
    {
        $tenant = Tenant::query()->create(['displayName' => 'Acme', 'state' => TenantState::Active]);
        TenantMembership::query()->create([
            'idTenant' => $tenant->id,
            'idUser' => $user->id,
            'role' => TenantMembershipRole::Owner,
            'state' => TenantMembershipState::Active,
        ]);

        return $tenant;
    }
}
