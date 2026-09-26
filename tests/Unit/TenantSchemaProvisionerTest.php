<?php

namespace Tests\Unit;

use App\Infrastructure\Tenant\TenantProvisioningConnectionFactory;
use App\Infrastructure\Tenant\TenantSchemaName;
use App\Services\Tenant\TenantSchemaProvisioner;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TenantSchemaProvisionerTest extends TestCase
{
    private const TENANT_ID = '42';

    public function test_it_applies_only_the_tenant_catalog_and_confirms_its_baseline(): void
    {
        $serverConnection = Mockery::mock(ConnectionInterface::class);
        $serverConnection->shouldReceive('selectOne')
            ->once()
            ->with(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                ['rinosone_42'],
            )
            ->andReturn((object) ['SCHEMA_NAME' => 'rinosone_42']);
        $serverConnection->shouldNotReceive('unprepared');

        $migrationQuery = Mockery::mock();
        $migrationQuery->shouldReceive('pluck')
            ->once()
            ->with('migration')
            ->andReturn(collect(['0001_01_01_000000_create_tenant_migration_baseline']));

        $tenantConnection = Mockery::mock(ConnectionInterface::class);
        $tenantConnection->shouldReceive('getName')->once()->andReturn('tenant-provisioning-test');
        $tenantConnection->shouldReceive('table')->once()->with('migrations')->andReturn($migrationQuery);

        $connections = $this->mock(TenantProvisioningConnectionFactory::class, function (MockInterface $mock) use ($serverConnection, $tenantConnection): void {
            $mock->shouldReceive('serverConnection')->once()->andReturn($serverConnection);
            $mock->shouldReceive('tenantConnection')->once()->with(self::TENANT_ID)->andReturn($tenantConnection);
        });

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate', Mockery::on(function (array $arguments): bool {
                return $arguments['--database'] === 'tenant-provisioning-test'
                    && $arguments['--path'] === database_path('migrations/tenant')
                    && $arguments['--realpath'] === true
                    && $arguments['--force'] === true;
            }))
            ->andReturn(0);

        (new TenantSchemaProvisioner(app(TenantSchemaName::class), $connections))->prepare(self::TENANT_ID);
    }
}
