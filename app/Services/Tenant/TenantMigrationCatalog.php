<?php

namespace App\Services\Tenant;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migrator;
use LogicException;

/**
 * Reads the tenant migration catalog distributed with the application and
 * compares it with a single tenant schema's applied migration history.
 */
class TenantMigrationCatalog
{
    public function __construct(private readonly Migrator $migrator) {}

    public function targetCatalog(): string
    {
        $migrations = $this->expectedMigrations();

        return hash('sha256', implode("\n", $migrations));
    }

    public function isComplete(ConnectionInterface $connection): bool
    {
        $appliedMigrations = $connection
            ->table(config('database.migrations.table'))
            ->pluck('migration')
            ->all();

        return array_diff($this->expectedMigrations(), $appliedMigrations) === [];
    }

    /**
     * @return list<string>
     */
    public function expectedMigrations(): array
    {
        $migrations = array_keys($this->migrator->getMigrationFiles([$this->migrationPath()]));

        if ($migrations === []) {
            throw new LogicException('The tenant migration catalog is unavailable.');
        }

        sort($migrations, SORT_STRING);

        return $migrations;
    }

    public function migrationPath(): string
    {
        $path = config('schema-compatibility.tenantMigrationPath');

        if (! is_string($path) || ! is_dir($path)) {
            throw new LogicException('The tenant migration path is unavailable.');
        }

        return $path;
    }
}
