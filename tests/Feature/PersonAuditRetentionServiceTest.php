<?php

namespace Tests\Feature;

use App\Domain\Tenant\TenantProvisioningState;
use App\Domain\Tenant\TenantState;
use App\Infrastructure\Tenant\TenantDatabaseConnectionFactory;
use App\Models\Tenant;
use App\Models\TenantProvisioning;
use App\Models\User;
use App\Services\Person\PersonAuditRetentionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PersonAuditRetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_purges_only_expired_events_from_successfully_provisioned_tenants(): void
    {
        $user = User::factory()->create();
        $readyTenant = Tenant::query()->create(['displayName' => 'Ready', 'state' => TenantState::Active]);
        $queuedTenant = Tenant::query()->create(['displayName' => 'Queued', 'state' => TenantState::Provisioning]);
        TenantProvisioning::query()->create(['idTenant' => $readyTenant->id, 'idRequestedByUser' => $user->id, 'idempotencyKey' => 'ready-1', 'state' => TenantProvisioningState::Succeeded, 'attemptCount' => 1, 'completedAt' => now()]);
        TenantProvisioning::query()->create(['idTenant' => $queuedTenant->id, 'idRequestedByUser' => $user->id, 'idempotencyKey' => 'queued-1', 'state' => TenantProvisioningState::Queued, 'attemptCount' => 0]);

        $connection = $this->tenantConnection('person_audit_retention_ready');
        $now = CarbonImmutable::parse('2026-09-29 12:00:00');
        $connection->table('personAuditEvent')->insert([
            ['personId' => 1, 'action' => 'CREATED', 'occurredAt' => $now->subDays(90)],
            ['personId' => 2, 'action' => 'UPDATED', 'occurredAt' => $now->subDays(90)->addSecond()],
        ]);
        $factory = Mockery::mock(TenantDatabaseConnectionFactory::class);
        $factory->shouldReceive('connection')->once()->with((string) $readyTenant->id)->andReturn($connection);

        $result = (new PersonAuditRetentionService($factory))->purgeExpired($now);

        $this->assertSame(1, $result->processedTenantCount);
        $this->assertSame(1, $result->deletedEventCount);
        $this->assertSame(1, $connection->table('personAuditEvent')->count());
    }

    private function tenantConnection(string $name): ConnectionInterface
    {
        config()->set("database.connections.{$name}", ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge($name);
        $connection = DB::connection($name);
        Schema::connection($name)->create('personAuditEvent', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('personId');
            $table->string('action');
            $table->timestamp('occurredAt');
        });

        return $connection;
    }
}
