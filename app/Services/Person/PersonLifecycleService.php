<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Person\PersonAuditAction;
use App\Domain\Person\PersonStatus;
use App\Models\Person;
use Illuminate\Database\ConnectionInterface;

class PersonLifecycleService
{
    public function __construct(private readonly PersonAuditLogger $audit) {}

    public function inactivate(ConnectionInterface $connection, int $personId, ?int $expectedVersion = null, ?int $actorUserId = null, ?string $correlationId = null): Person
    {
        return $this->transition($connection, $personId, PersonStatus::INACTIVE, $expectedVersion, $actorUserId, $correlationId);
    }

    public function reactivate(ConnectionInterface $connection, int $personId, ?int $expectedVersion = null, ?int $actorUserId = null, ?string $correlationId = null): Person
    {
        return $this->transition($connection, $personId, PersonStatus::ACTIVE, $expectedVersion, $actorUserId, $correlationId);
    }

    private function transition(ConnectionInterface $connection, int $personId, PersonStatus $status, ?int $expectedVersion, ?int $actorUserId, ?string $correlationId): Person
    {
        $person = (new Person)->forTenantConnection($connection)->newQuery()->find($personId);
        if ($person === null) {
            throw new PersonValidationException(['person' => 'not_found']);
        }
        if ($expectedVersion !== null && $person->version !== $expectedVersion) {
            throw new PersonVersionConflictException;
        }
        if ($person->status !== $status) {
            $updated = (new Person)->forTenantConnection($connection)->newQuery()
                ->whereKey($personId)
                ->where('version', $person->version)
                ->update(['status' => $status, 'version' => $person->version + 1]);
            if ($updated !== 1) {
                throw new PersonVersionConflictException;
            }
            $this->audit->record(
                $connection,
                $personId,
                $status === PersonStatus::INACTIVE ? PersonAuditAction::INACTIVATED : PersonAuditAction::REACTIVATED,
                $actorUserId,
                $correlationId,
            );
            $person = (new Person)->forTenantConnection($connection)->newQuery()->findOrFail($personId);
        }

        return $person;
    }
}
