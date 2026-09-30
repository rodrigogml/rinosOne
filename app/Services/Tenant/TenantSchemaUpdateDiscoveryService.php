<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\TenantState;
use App\Infrastructure\Tenant\TenantProvisioningConnectionFactory;
use App\Jobs\Tenant\UpdateTenantSchema;
use App\Models\Tenant;
use Throwable;

/**
 * Finds active tenants whose schema does not contain the current catalog and
 * creates a single supervised update lifecycle for each affected tenant.
 */
class TenantSchemaUpdateDiscoveryService
{
    public function __construct(
        private readonly TenantMigrationCatalog $catalog,
        private readonly TenantProvisioningConnectionFactory $connections,
        private readonly TenantSchemaUpdateLifecycle $lifecycle,
    ) {}

    public function discover(): int
    {
        $targetCatalog = $this->catalog->targetCatalog();
        $createdCount = 0;

        Tenant::query()
            ->where('state', TenantState::Active)
            ->orderBy('id')
            ->eachById(function (Tenant $tenant) use ($targetCatalog, &$createdCount): void {
                if ($this->isComplete($tenant)) {
                    return;
                }

                $update = $this->lifecycle->queue((string) $tenant->id, $targetCatalog);

                if ($update->wasRecentlyCreated) {
                    UpdateTenantSchema::dispatch($update->id)->afterCommit();
                    $createdCount++;
                }
            });

        return $createdCount;
    }

    private function isComplete(Tenant $tenant): bool
    {
        try {
            return $this->catalog->isComplete($this->connections->tenantConnection((string) $tenant->id));
        } catch (Throwable) {
            return false;
        }
    }
}
