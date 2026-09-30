<?php

namespace App\Domain\Person\Deletion;

use Illuminate\Database\ConnectionInterface;

/**
 * Reports known usages in one tenant schema that block physical deletion of a
 * Person. Implementations must return only generic, user-actionable messages.
 */
interface PersonUsageInspector
{
    /** @return list<PersonUsage> */
    public function inspect(ConnectionInterface $connection, int $personId): array;
}
