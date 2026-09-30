<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\SchemaUpdate\TenantSchemaUpdateAttempt;
use App\Infrastructure\Tenant\TenantProvisioningConnectionFactory;
use Illuminate\Support\Facades\Artisan;
use LogicException;

/**
 * Applies the tenant migration catalog to an existing schema through the
 * provisioning connection and confirms the catalog after execution.
 */
class TenantSchemaUpdateExecutor
{
    public function __construct(
        private readonly TenantProvisioningConnectionFactory $connections,
        private readonly TenantMigrationCatalog $catalog,
    ) {}

    public function execute(TenantSchemaUpdateAttempt $attempt): void
    {
        $connection = $this->connections->tenantConnection($attempt->tenantId);
        $exitCode = Artisan::call('migrate', [
            '--database' => $connection->getName(),
            '--path' => $this->catalog->migrationPath(),
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($exitCode !== 0 || ! $this->catalog->isComplete($connection)) {
            throw new LogicException('The tenant migration catalog could not be applied completely.');
        }
    }
}
