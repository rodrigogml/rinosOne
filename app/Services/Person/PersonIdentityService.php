<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonDocumentConflictException;
use App\Domain\Person\Exception\PersonVersionConflictException;
use App\Domain\Person\PersonAuditAction;
use App\Domain\Person\PersonIdentityInput;
use App\Domain\Person\PersonIdentityValidator;
use App\Models\Person;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PersonIdentityService
{
    public function __construct(
        private readonly PersonIdentityValidator $validator,
        private readonly PersonAuditLogger $audit,
    ) {}

    /**
     * Creates one Person identity in the already authorized tenant connection.
     *
     * @throws PersonDocumentConflictException when the tenant already owns an informed CPF or CNPJ.
     */
    public function create(ConnectionInterface $connection, PersonIdentityInput $input, ?int $actorUserId = null, ?string $correlationId = null): Person
    {
        $attributes = $this->validator->validate($input);

        try {
            return $connection->transaction(function () use ($connection, $attributes, $actorUserId, $correlationId): Person {
                $person = (new Person)->forTenantConnection($connection);
                $person->fill($attributes);
                $person->save();
                $this->audit->record($connection, $person->id, PersonAuditAction::CREATED, $actorUserId, $correlationId);

                return $person->newQuery()->findOrFail($person->id);
            });
        } catch (QueryException $exception) {
            throw $this->documentConflictOrRethrow($exception);
        }
    }

    /**
     * Replaces identity fields only when the version read by the caller remains current.
     *
     * @throws PersonVersionConflictException when a concurrent change makes the supplied version stale.
     * @throws PersonDocumentConflictException when the updated document conflicts inside this tenant.
     */
    public function update(ConnectionInterface $connection, int $personId, int $expectedVersion, PersonIdentityInput $input, ?int $actorUserId = null, ?string $correlationId = null): Person
    {
        $attributes = $this->validator->validate($input);
        if ($personId < 1 || $expectedVersion < 1) {
            throw new PersonVersionConflictException;
        }

        try {
            return $connection->transaction(function () use ($connection, $personId, $expectedVersion, $attributes, $actorUserId, $correlationId): Person {
                $model = (new Person)->forTenantConnection($connection);
                $updated = $model->newQuery()
                    ->whereKey($personId)
                    ->where('version', $expectedVersion)
                    ->update([...$attributes, 'version' => DB::raw('version + 1')]);
                if ($updated !== 1) {
                    throw new PersonVersionConflictException;
                }
                $this->audit->record($connection, $personId, PersonAuditAction::UPDATED, $actorUserId, $correlationId);

                return $model->newQuery()->findOrFail($personId);
            });
        } catch (QueryException $exception) {
            throw $this->documentConflictOrRethrow($exception);
        }
    }

    private function documentConflictOrRethrow(QueryException $exception): PersonDocumentConflictException
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'uk_person_cpf') || str_contains($message, 'uk_person_cnpj') || str_contains($message, 'person.cpf') || str_contains($message, 'person.cnpj')) {
            return new PersonDocumentConflictException;
        }

        throw $exception;
    }
}
