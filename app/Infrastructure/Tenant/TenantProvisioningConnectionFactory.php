<?php

namespace App\Infrastructure\Tenant;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use LogicException;

class TenantProvisioningConnectionFactory
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly TenantSchemaName $schemaName,
    ) {}

    public function serverConnection(): ConnectionInterface
    {
        return $this->database->build($this->baseConfiguration());
    }

    public function tenantConnection(string $tenantId): ConnectionInterface
    {
        $configuration = $this->baseConfiguration();
        $configuration['database'] = $this->schemaName->fromTenantId($tenantId);

        return $this->database->build($configuration);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConfiguration(): array
    {
        $configuration = config('database.connections.provisioning');

        if (! is_array($configuration)) {
            throw new LogicException('The provisioning database connection configuration is unavailable.');
        }

        return $configuration;
    }
}
