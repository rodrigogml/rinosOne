<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantMembershipRole;
use App\Domain\Tenant\TenantMembershipState;
use App\Domain\Tenant\TenantProvisioningState;
use App\Domain\Tenant\TenantState;
use App\Jobs\ProvisionTenantSchema;
use App\Models\User;
use App\Services\Tenant\TenantCreationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_provisioning_tenant_and_its_only_owner_membership(): void
    {
        Queue::fake();
        $creator = User::factory()->create();
        $idempotencyKey = (string) Str::ulid();

        $result = app(TenantCreationService::class)->create($creator, 'Acme Serviços', $idempotencyKey);

        $this->assertTrue($result->created);
        $this->assertSame(TenantState::Provisioning, $result->tenant->state);
        $this->assertSame(TenantProvisioningState::Queued, $result->provisioning->state);
        $this->assertDatabaseHas('tenantMembership', [
            'idTenant' => $result->tenant->id,
            'idUser' => $creator->id,
            'role' => TenantMembershipRole::Owner->value,
            'state' => TenantMembershipState::Active->value,
        ]);
        $this->assertDatabaseCount('tenantMembership', 1);
        Queue::assertPushed(ProvisionTenantSchema::class, function (ProvisionTenantSchema $job) use ($result): bool {
            return $job->provisioningId === $result->provisioning->id;
        });
    }

    public function test_repeating_an_intent_returns_the_same_tenant_without_duplicate_records_or_job(): void
    {
        Queue::fake();
        $creator = User::factory()->create();
        $idempotencyKey = (string) Str::ulid();
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
        $creator->id = (string) Str::ulid();

        try {
            app(TenantCreationService::class)->create($creator, 'Acme Serviços', (string) Str::ulid());
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

        app(TenantCreationService::class)->create(User::factory()->create(), 'Acme Serviços', 'not-a-ulid');
    }
}
