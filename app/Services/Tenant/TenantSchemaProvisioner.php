<?php

namespace App\Services\Tenant;

use App\Infrastructure\Tenant\TenantProvisioningConnectionFactory;
use App\Infrastructure\Tenant\TenantSchemaName;
use Illuminate\Support\Facades\Artisan;
use LogicException;

class TenantSchemaProvisioner
{
    public function __construct(
        private readonly TenantSchemaName $schemaName,
        private readonly TenantProvisioningConnectionFactory $connections,
    ) {}

    public function prepare(string $tenantId): void
    {
        $schema = $this->schemaName->fromTenantId($tenantId);
        $serverConnection = $this->connections->serverConnection();
        $schemaExists = $serverConnection->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$schema],
        );

        if ($schemaExists === null) {
            $serverConnection->unprepared(
                "CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            );
        }

        $tenantConnection = $this->connections->tenantConnection($tenantId);
        $exitCode = Artisan::call('migrate', [
            '--database' => $tenantConnection->getName(),
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new LogicException('The tenant migration catalog could not be applied.');
        }

        $expectedMigrations = array_keys(app('migrator')->getMigrationFiles([database_path('migrations/tenant')]));
        $appliedMigrations = $tenantConnection
            ->table(config('database.migrations.table'))
            ->pluck('migration')
            ->all();

        if (array_diff($expectedMigrations, $appliedMigrations) !== []) {
            throw new LogicException('The tenant migration catalog is incomplete.');
        }
    }
}
