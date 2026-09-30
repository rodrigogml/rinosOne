<?php

namespace Tests\Unit;

use App\Domain\Tenant\Exception\PlatformSchemaIncompatibleException;
use App\Domain\Tenant\Exception\TenantSchemaUnavailableException;
use App\Services\Tenant\GlobalSchemaCompatibilityService;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GlobalSchemaCompatibilityServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_accepts_a_complete_global_migration_history(): void
    {
        $service = $this->serviceWithAppliedMigrations([
            '2026_01_01_000000_create_first_table',
            '2026_01_02_000000_create_second_table',
        ]);

        $this->assertTrue($service->decide()->isCompatible());
    }

    public function test_it_rejects_a_pending_global_migration(): void
    {
        $service = $this->serviceWithAppliedMigrations([
            '2026_01_01_000000_create_first_table',
        ]);

        $this->assertFalse($service->decide()->isCompatible());
    }

    public function test_it_rejects_an_empty_migration_history(): void
    {
        $service = $this->serviceWithAppliedMigrations([]);

        $this->assertFalse($service->decide()->isCompatible());
    }

    public function test_it_rejects_an_unreadable_migration_history(): void
    {
        $migrator = $this->migrator();
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->once()->with('core')->andThrow(new RuntimeException('unavailable'));

        $service = new GlobalSchemaCompatibilityService($migrator, $database);

        $this->assertFalse($service->decide()->isCompatible());
    }

    public function test_it_revalidates_after_the_compatible_decision_window_expires(): void
    {
        Carbon::setTestNow('2026-09-30 12:00:00');
        config()->set('schema-compatibility.globalRevalidationSeconds', 60);

        $migrator = $this->migrator(2);
        $query = Mockery::mock();
        $query->shouldReceive('pluck')->twice()->with('migration')->andReturn(collect([
            '2026_01_01_000000_create_first_table',
            '2026_01_02_000000_create_second_table',
        ]));
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('table')->twice()->with('migrations')->andReturn($query);
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->twice()->with('core')->andReturn($connection);
        $service = new GlobalSchemaCompatibilityService($migrator, $database);

        $this->assertTrue($service->decide()->isCompatible());
        $this->assertTrue($service->decide()->isCompatible());

        Carbon::setTestNow('2026-09-30 12:01:01');

        $this->assertTrue($service->decide()->isCompatible());
    }

    public function test_it_defines_stable_public_domain_error_codes(): void
    {
        $this->assertSame('PLATFORM_SCHEMA_INCOMPATIBLE', PlatformSchemaIncompatibleException::ERROR_CODE);
        $this->assertSame('TENANT_SCHEMA_UNAVAILABLE', TenantSchemaUnavailableException::ERROR_CODE);
    }

    /**
     * @param  array<int, string>  $appliedMigrations
     */
    private function serviceWithAppliedMigrations(array $appliedMigrations): GlobalSchemaCompatibilityService
    {
        $migrator = $this->migrator();
        $query = Mockery::mock();
        $query->shouldReceive('pluck')->once()->with('migration')->andReturn(collect($appliedMigrations));
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('table')->once()->with('migrations')->andReturn($query);
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->once()->with('core')->andReturn($connection);

        return new GlobalSchemaCompatibilityService($migrator, $database);
    }

    private function migrator(int $times = 1): Migrator
    {
        $migrator = Mockery::mock(Migrator::class);
        $migrator->shouldReceive('paths')->times($times)->andReturn([database_path('migrations/core')]);
        $migrator->shouldReceive('getMigrationFiles')
            ->times($times)
            ->with([database_path('migrations/core')])
            ->andReturn([
                '2026_01_01_000000_create_first_table' => 'first.php',
                '2026_01_02_000000_create_second_table' => 'second.php',
            ]);

        return $migrator;
    }
}
