<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantProvisioningState;
use App\Domain\Tenant\TenantState;
use App\Jobs\ProvisionTenantSchema;
use App\Models\AuthorizationRole;
use App\Models\User;
use App\Services\Tenant\TenantCreationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_provisioning_tenant_membership_and_administrator_assignment(): void
    {
        Queue::fake();
        $creator = User::factory()->create();
        $idempotencyKey = (string) str()->uuid();

        $result = app(TenantCreationService::class)->create($creator, 'Acme Serviços', $idempotencyKey);

        $this->assertTrue($result->created);
        $this->assertSame(TenantState::Provisioning, $result->tenant->state);
        $this->assertSame(TenantProvisioningState::Queued, $result->provisioning->state);
        $this->assertDatabaseHas('tenantMembership', [
            'idTenant' => $result->tenant->id,
            'idUser' => $creator->id,
            'state' => TenantMembershipState::Active->value,
        ]);
        $this->assertDatabaseCount('tenantMembership', 1);
        $this->assertDatabaseHas('auth_role_assignment', [
            'idUser' => $creator->id,
            'idTenant' => $result->tenant->id,
            'state' => 'ACTIVE',
        ]);
        Queue::assertPushed(ProvisionTenantSchema::class, function (ProvisionTenantSchema $job) use ($result): bool {
            return $job->provisioningId === $result->provisioning->id;
        });
    }

    public function test_repeating_an_intent_returns_the_same_tenant_without_duplicate_records_or_job(): void
    {
        Queue::fake();
        $creator = User::factory()->create();
        $idempotencyKey = (string) str()->uuid();
        $service = app(TenantCreationService::class);

        $first = $service->create($creator, 'Acme Serviços', $idempotencyKey);
        $second = $service->create($creator, 'Nome ignorado na repetição', $idempotencyKey);

        $this->assertTrue($first->created);
        $this->assertFalse($second->created);
        $this->assertSame($first->tenant->id, $second->tenant->id);
        $this->assertSame($first->provisioning->id, $second->provisioning->id);
        $this->assertDatabaseCount('tenant', 1);
        $this->assertDatabaseCount('tenantMembership', 1);
        $this->assertDatabaseCount('tenantProvisioning', 1);
        Queue::assertPushed(ProvisionTenantSchema::class, 1);
    }

    public function test_failed_membership_creation_rolls_back_the_tenant_and_does_not_dispatch_the_job(): void
    {
        Queue::fake();
        $creator = new User;
        $creator->id = 999999;

        try {
            app(TenantCreationService::class)->create($creator, 'Acme Serviços', (string) str()->uuid());
            $this->fail('Expected a database constraint violation.');
        } catch (QueryException) {
            $this->assertDatabaseCount('tenant', 0);
            $this->assertDatabaseCount('tenantMembership', 0);
            $this->assertDatabaseCount('tenantProvisioning', 0);
            Queue::assertNothingPushed();
        }
    }

    public function test_it_rejects_an_invalid_idempotency_key_before_persisting_data(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(TenantCreationService::class)->create(User::factory()->create(), 'Acme Serviços', 'not-a-uuid');
    }

    public function test_tenant_creation_does_not_assign_a_platform_role(): void
    {
        Queue::fake();
        $creator = User::factory()->create();
        $platformRole = AuthorizationRole::query()->firstOrCreate(
            ['key' => 'platform.administrator'],
            [
                'displayName' => 'Platform administrator', 'description' => 'Platform.',
                'scope' => 'PLATFORM', 'type' => 'SYSTEM', 'systemManaged' => true, 'active' => true,
            ],
        );

        app(TenantCreationService::class)->create($creator, 'Acme Serviços', (string) str()->uuid());

        $this->assertDatabaseMissing('auth_role_assignment', ['idRole' => $platformRole->id, 'idUser' => $creator->id]);
    }
}
