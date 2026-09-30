<?php

namespace App\Services\Tenant;

use App\Domain\Tenant\SchemaCompatibilityDecision;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Decides whether the global schema contains the entire migration catalog
 * distributed with the running application.
 *
 * A database or catalog read failure is deliberately represented as an
 * incompatible decision. Consumers must not receive the underlying details.
 */
class GlobalSchemaCompatibilityService
{
    private ?SchemaCompatibilityDecision $cachedDecision = null;

    private ?CarbonImmutable $checkedAt = null;

    public function __construct(
        private readonly Migrator $migrator,
        private readonly DatabaseManager $database,
    ) {}

    public function decide(): SchemaCompatibilityDecision
    {
        $revalidationSeconds = config('schema-compatibility.globalRevalidationSeconds');

        if (! is_int($revalidationSeconds) || $revalidationSeconds < 0) {
            return SchemaCompatibilityDecision::incompatible();
        }

        $now = CarbonImmutable::now();

        if ($this->canReuseCompatibleDecision($now, $revalidationSeconds)) {
            return $this->cachedDecision;
        }

        try {
            $expectedMigrations = array_keys($this->migrator->getMigrationFiles($this->migrator->paths()));

            if ($expectedMigrations === []) {
                return SchemaCompatibilityDecision::incompatible();
            }

            $appliedMigrations = $this->database
                ->connection('core')
                ->table(config('database.migrations.table'))
                ->pluck('migration')
                ->all();

            $decision = array_diff($expectedMigrations, $appliedMigrations) === []
                ? SchemaCompatibilityDecision::compatible()
                : SchemaCompatibilityDecision::incompatible();
        } catch (Throwable) {
            return SchemaCompatibilityDecision::incompatible();
        }

        if ($decision->isCompatible() && $revalidationSeconds > 0) {
            $this->cachedDecision = $decision;
            $this->checkedAt = $now;
        } else {
            $this->cachedDecision = null;
            $this->checkedAt = null;
        }

        return $decision;
    }

    private function canReuseCompatibleDecision(CarbonImmutable $now, int $revalidationSeconds): bool
    {
        return $revalidationSeconds > 0
            && $this->cachedDecision?->isCompatible()
            && $this->checkedAt !== null
            && $this->checkedAt->addSeconds($revalidationSeconds)->isAfter($now);
    }
}
