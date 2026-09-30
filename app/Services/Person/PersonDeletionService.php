<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonDeletionConflictException;
use App\Domain\Person\Exception\PersonInUseException;
use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Person\PersonAuditAction;
use App\Models\Person;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

class PersonDeletionService
{
    public function __construct(
        private readonly PersonDeletionUsageService $usageService,
        private readonly PersonAuditLogger $audit,
    ) {}

    /**
     * Physically removes one Person after checking the known usages in the
     * active tenant schema. Child data and relationships are removed by the
     * schema cascades; the related counterparty is never deleted.
     *
     * @throws PersonInUseException when a registered module blocks deletion
     * @throws PersonDeletionConflictException when an unknown integrity rule blocks deletion
     */
    public function delete(ConnectionInterface $connection, int $personId, ?int $expectedVersion = null, ?int $actorUserId = null, ?string $correlationId = null): void
    {
        try {
            $connection->transaction(function () use ($connection, $personId, $expectedVersion, $actorUserId, $correlationId): void {
                $person = (new Person)->forTenantConnection($connection)->newQuery()->find($personId);
                if ($person === null) {
                    throw new PersonValidationException(['person' => 'not_found']);
                }
                if ($expectedVersion !== null && $person->version !== $expectedVersion) {
                    throw new PersonVersionConflictException;
                }

                $usages = $this->usageService->inspect($connection, $personId);
                if ($usages !== []) {
                    throw new PersonInUseException($usages);
                }

                $this->audit->record($connection, $person->id, PersonAuditAction::DELETED, $actorUserId, $correlationId);
                $deleted = (new Person)->forTenantConnection($connection)->newQuery()
                    ->whereKey($personId)
                    ->when($expectedVersion !== null, fn ($query) => $query->where('version', $expectedVersion))
                    ->delete();
                if ($deleted !== 1) {
                    throw new PersonVersionConflictException;
                }
            });
        } catch (QueryException $exception) {
            if ($this->isIntegrityConflict($exception)) {
                throw new PersonDeletionConflictException;
            }

            throw $exception;
        }
    }

    private function isIntegrityConflict(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'foreign key constraint')
            || str_contains($message, 'cannot delete or update a parent row')
            || str_contains($message, 'integrity constraint violation');
    }
}
