<?php

namespace App\Infrastructure\Tenant;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use LogicException;

class TenantDatabaseConnectionFactory
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly TenantSchemaName $schemaName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function configuration(string $tenantId): array
    {
        $configuration = config('database.connections.tenant');

        if (! is_array($configuration)) {
            throw new LogicException('The tenant database connection configuration is unavailable.');
        }

        $configuration['database'] = $this->schemaName->fromTenantId($tenantId);

        return $configuration;
    }

    public function connection(string $tenantId): ConnectionInterface
    {
        return $this->database->build($this->configuration($tenantId));
    }
}
