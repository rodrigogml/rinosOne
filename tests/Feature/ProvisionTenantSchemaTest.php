<?php

namespace Tests\Feature;

use App\Jobs\ProvisionTenantSchema;
use App\Models\User;
use App\Services\Tenant\TenantProvisioningFailureClassifier;
use App\Services\Tenant\TenantProvisioningLifecycle;
use App\Services\Tenant\TenantSchemaProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery\MockInterface;
use PDOException;
use Tests\TestCase;

class ProvisionTenantSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_provisioning_activates_only_the_matching_tenant(): void
    {
        [$provisioningId, $tenantId] = $this->createProvisioning();

        $this->mock(TenantSchemaProvisioner::class, function (MockInterface $mock) use ($tenantId): void {
            $mock->shouldReceive('prepare')->once()->with($tenantId);
        });

        $this->runJob($provisioningId);

        $this->assertDatabaseHas('tenantProvisioning', [
            'id' => $provisioningId,
            'state' => 'SUCCEEDED',
            'attemptCount' => 1,
        ]);
        $this->assertDatabaseHas('tenant', ['id' => $tenantId, 'state' => 'ACTIVE']);
    }

    public function test_completed_provisioning_is_not_run_again(): void
    {
        [$provisioningId, $tenantId] = $this->createProvisioning('SUCCEEDED', 'ACTIVE', 1);

        $this->mock(TenantSchemaProvisioner::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('prepare');
        });

        $this->runJob($provisioningId);

        $this->assertDatabaseHas('tenantProvisioning', ['id' => $provisioningId, 'state' => 'SUCCEEDED']);
        $this->assertDatabaseHas('tenant', ['id' => $tenantId, 'state' => 'ACTIVE']);
    }

    public function test_transient_failure_requeues_the_same_operation_with_a_safe_code(): void
    {
        [$provisioningId, $tenantId] = $this->createProvisioning();

        $this->mock(TenantSchemaProvisioner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('prepare')->once()->andThrow(new PDOException('Connection unavailable', 2002));
        });

        $this->runJob($provisioningId);

        $this->assertDatabaseHas('tenantProvisioning', [
            'id' => $provisioningId,
            'state' => 'QUEUED',
            'attemptCount' => 1,
            'lastFailureCode' => 'TENANT_PROVISIONING_DATABASE_TRANSIENT',
        ]);
        $this->assertDatabaseHas('tenant', ['id' => $tenantId, 'state' => 'PROVISIONING']);
    }

    public function test_terminal_failure_marks_the_operation_and_tenant_as_failed(): void
    {
        [$provisioningId, $tenantId] = $this->createProvisioning();

        $this->mock(TenantSchemaProvisioner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('prepare')->once()->andThrow(new InvalidArgumentException('Invalid schema'));
        });

        $this->runJob($provisioningId);

        $this->assertDatabaseHas('tenantProvisioning', [
            'id' => $provisioningId,
            'state' => 'FAILED',
            'lastFailureCode' => 'TENANT_PROVISIONING_CONFIGURATION_INVALID',
        ]);
        $this->assertDatabaseHas('tenant', ['id' => $tenantId, 'state' => 'FAILED']);
    }

    public function test_transient_failures_stop_after_the_configured_maximum_attempts(): void
    {
        [$provisioningId, $tenantId] = $this->createProvisioning();

        $this->mock(TenantSchemaProvisioner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('prepare')->times(3)->andThrow(new PDOException('Connection unavailable', 2002));
        });

        $this->runJob($provisioningId);
        $this->runJob($provisioningId);
        $this->runJob($provisioningId);

        $this->assertDatabaseHas('tenantProvisioning', [
            'id' => $provisioningId,
            'state' => 'FAILED',
            'attemptCount' => 3,
            'lastFailureCode' => 'TENANT_PROVISIONING_DATABASE_TRANSIENT',
        ]);
        $this->assertDatabaseHas('tenant', ['id' => $tenantId, 'state' => 'FAILED']);
    }

    /**
     * @return array{int, int}
     */
    private function createProvisioning(
        string $provisioningState = 'QUEUED',
        string $tenantState = 'PROVISIONING',
        int $attemptCount = 0,
    ): array {
        $user = User::factory()->create();
        DB::table('tenant')->insert([
            'displayName' => 'Tenant test',
            'state' => $tenantState,
        ]);
        $tenantId = (int) DB::table('tenant')->max('id');
        DB::table('tenantProvisioning')->insert([
            'idTenant' => $tenantId,
            'idRequestedByUser' => $user->id,
            'idempotencyKey' => (string) str()->uuid(),
            'state' => $provisioningState,
            'attemptCount' => $attemptCount,
            'completedAt' => $provisioningState === 'SUCCEEDED' ? now() : null,
        ]);
        $provisioningId = (int) DB::table('tenantProvisioning')->max('id');

        return [$provisioningId, $tenantId];
    }

    private function runJob(int $provisioningId): void
    {
        (new ProvisionTenantSchema($provisioningId))->handle(
            app(TenantProvisioningLifecycle::class),
            app(TenantSchemaProvisioner::class),
            app(TenantProvisioningFailureClassifier::class),
        );
    }
}
