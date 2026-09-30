<?php

namespace Tests\Feature;

use App\Domain\Tenant\SchemaUpdate\TenantSchemaUpdateAttempt;
use App\Domain\Tenant\TenantSchemaUpdateState;
use App\Infrastructure\Tenant\TenantProvisioningConnectionFactory;
use App\Jobs\Tenant\DiscoverTenantSchemaUpdates;
use App\Jobs\Tenant\UpdateTenantSchema;
use App\Models\Tenant;
use App\Services\Tenant\TenantMigrationCatalog;
use App\Services\Tenant\TenantSchemaUpdateDiscoveryService;
use App\Services\Tenant\TenantSchemaUpdateExecutor;
use App\Services\Tenant\TenantSchemaUpdateFailureClassifier;
use App\Services\Tenant\TenantSchemaUpdateLifecycle;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use PDOException;
use Tests\TestCase;

class TenantSchemaUpdateExecutionTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDatabasePath = tempnam(sys_get_temp_dir(), 'rinos-tenant-schema-update-');
        config()->set('database.connections.tenant-schema-update-test', [
            'driver' => 'sqlite',
            'database' => $this->tenantDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('schema-compatibility.tenantMigrationPath', base_path('tests/Fixtures/TenantSchemaUpdateMigrations'));
    }

    protected function tearDown(): void
    {
        DB::purge('tenant-schema-update-test');
        @unlink($this->tenantDatabasePath);

        parent::tearDown();
    }

    public function test_executor_applies_and_validates_a_disposable_tenant_catalog(): void
    {
        $connection = DB::connection('tenant-schema-update-test');
        $connections = Mockery::mock(TenantProvisioningConnectionFactory::class);
        $connections->shouldReceive('tenantConnection')->once()->with('1')->andReturn($connection);
        $executor = new TenantSchemaUpdateExecutor($connections, app(TenantMigrationCatalog::class));

        $executor->execute(new TenantSchemaUpdateAttempt('1', '1', 'fixture', 1));

        $this->assertTrue($connection->getSchemaBuilder()->hasTable('tenantSchemaUpdateFixture'));
        $this->assertTrue(app(TenantMigrationCatalog::class)->isComplete($connection));
    }

    public function test_discovery_creates_and_dispatches_one_update_for_an_incomplete_active_tenant(): void
    {
        Queue::fake();
        $tenant = Tenant::query()->create(['displayName' => 'Incomplete tenant', 'state' => 'ACTIVE']);
        $inactiveTenant = Tenant::query()->create(['displayName' => 'Inactive tenant', 'state' => 'INACTIVE']);
        $connection = Mockery::mock(ConnectionInterface::class);
        $catalog = Mockery::mock(TenantMigrationCatalog::class);
        $catalog->shouldReceive('targetCatalog')->twice()->andReturn('target-v1');
        $catalog->shouldReceive('isComplete')->twice()->with($connection)->andReturn(false);
        $connections = Mockery::mock(TenantProvisioningConnectionFactory::class);
        $connections->shouldReceive('tenantConnection')->twice()->with((string) $tenant->id)->andReturn($connection);
        $discovery = new TenantSchemaUpdateDiscoveryService($catalog, $connections, app(TenantSchemaUpdateLifecycle::class));

        $this->assertSame(1, $discovery->discover());
        $this->assertSame(0, $discovery->discover());
        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'idTenant' => $tenant->id,
            'targetCatalog' => 'target-v1',
            'state' => TenantSchemaUpdateState::Queued->value,
        ]);
        $this->assertDatabaseMissing('tenantSchemaUpdate', ['idTenant' => $inactiveTenant->id]);
        Queue::assertPushed(UpdateTenantSchema::class, 1);
    }

    public function test_discovery_queues_a_tenant_when_its_history_cannot_be_read(): void
    {
        Queue::fake();
        $tenant = Tenant::query()->create(['displayName' => 'Unreadable tenant', 'state' => 'ACTIVE']);
        $catalog = Mockery::mock(TenantMigrationCatalog::class);
        $catalog->shouldReceive('targetCatalog')->once()->andReturn('target-v1');
        $catalog->shouldReceive('isComplete')->once()->andThrow(new \RuntimeException('unavailable'));
        $connections = Mockery::mock(TenantProvisioningConnectionFactory::class);
        $connections->shouldReceive('tenantConnection')->once()->with((string) $tenant->id)->andReturn(Mockery::mock(ConnectionInterface::class));
        $discovery = new TenantSchemaUpdateDiscoveryService($catalog, $connections, app(TenantSchemaUpdateLifecycle::class));

        $this->assertSame(1, $discovery->discover());
        $this->assertDatabaseHas('tenantSchemaUpdate', ['idTenant' => $tenant->id, 'targetCatalog' => 'target-v1']);
    }

    public function test_update_job_releases_a_transient_failure_and_completes_a_later_attempt(): void
    {
        $update = app(TenantSchemaUpdateLifecycle::class)->queue((string) $this->createTenant()->id, 'target-v1');
        $job = new UpdateTenantSchema($update->id);
        $this->mock(TenantSchemaUpdateExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new PDOException('unavailable', 2002));
        });

        $job->handle(
            app(TenantSchemaUpdateLifecycle::class),
            app(TenantSchemaUpdateExecutor::class),
            app(TenantSchemaUpdateFailureClassifier::class),
        );

        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'id' => $update->id,
            'state' => TenantSchemaUpdateState::Queued->value,
            'attemptCount' => 1,
            'lastFailureCode' => 'TENANT_SCHEMA_UPDATE_DATABASE_TRANSIENT',
        ]);

        $this->mock(TenantSchemaUpdateExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once();
        });
        $job->handle(
            app(TenantSchemaUpdateLifecycle::class),
            app(TenantSchemaUpdateExecutor::class),
            app(TenantSchemaUpdateFailureClassifier::class),
        );

        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'id' => $update->id,
            'state' => TenantSchemaUpdateState::Succeeded->value,
            'attemptCount' => 2,
        ]);
    }

    public function test_update_job_marks_a_terminal_failure_without_running_a_second_executor(): void
    {
        $update = app(TenantSchemaUpdateLifecycle::class)->queue((string) $this->createTenant()->id, 'target-v1');
        $job = new UpdateTenantSchema($update->id);
        $this->mock(TenantSchemaUpdateExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new LogicException('catalog invalid'));
        });

        $job->handle(
            app(TenantSchemaUpdateLifecycle::class),
            app(TenantSchemaUpdateExecutor::class),
            app(TenantSchemaUpdateFailureClassifier::class),
        );

        $this->assertDatabaseHas('tenantSchemaUpdate', [
            'id' => $update->id,
            'state' => TenantSchemaUpdateState::Failed->value,
            'lastFailureCode' => 'TENANT_SCHEMA_UPDATE_CATALOG_INVALID',
        ]);

        $this->mock(TenantSchemaUpdateExecutor::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('execute');
        });
        $job->handle(
            app(TenantSchemaUpdateLifecycle::class),
            app(TenantSchemaUpdateExecutor::class),
            app(TenantSchemaUpdateFailureClassifier::class),
        );
    }

    public function test_discovery_job_and_scheduler_registration_use_the_supervised_queue_path(): void
    {
        $this->mock(TenantSchemaUpdateDiscoveryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('discover')->once();
        });

        (new DiscoverTenantSchemaUpdates)->handle(app(TenantSchemaUpdateDiscoveryService::class));
        Artisan::call('schedule:list');

        $this->assertStringContainsString('tenant-schema-update-discovery', Artisan::output());
    }

    private function createTenant(): Tenant
    {
        return Tenant::query()->create(['displayName' => 'Update tenant', 'state' => 'ACTIVE']);
    }
}
