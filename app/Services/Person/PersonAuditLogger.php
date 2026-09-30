<?php

namespace App\Services\Person;

use App\Domain\Person\PersonAuditAction;
use App\Models\PersonAuditEvent;
use Illuminate\Database\ConnectionInterface;

/**
 * Appends the minimum Person lifecycle audit record in the active tenant.
 *
 * It never stores snapshots or values from the Person aggregate, preventing
 * personal, contact, financial, address, and Pix data from entering audit
 * retention through this module.
 */
class PersonAuditLogger
{
    public function record(
        ConnectionInterface $connection,
        int $personId,
        PersonAuditAction $action,
        ?int $actorUserId = null,
        ?string $correlationId = null,
    ): PersonAuditEvent {
        $event = (new PersonAuditEvent)->forTenantConnection($connection);
        $event->fill([
            'personId' => $personId,
            'idActorUser' => $actorUserId,
            'action' => $action,
            'occurredAt' => now(),
            'correlationId' => $correlationId,
        ]);
        $event->save();

        return $event;
    }
}
