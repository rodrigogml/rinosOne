<?php

namespace App\Services\Person;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonRelationshipType;
use App\Models\Person;
use App\Models\PersonRelationship;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

class PersonRelationshipService
{
    /**
     * Creates one directed relation after confirming both endpoints in the
     * authorized tenant schema. No reciprocal row is created.
     */
    public function create(ConnectionInterface $connection, int $sourcePersonId, int $targetPersonId, PersonRelationshipType $type, ?string $description = null): PersonRelationship
    {
        $this->assertValid($connection, $sourcePersonId, $targetPersonId, $description);

        try {
            return $connection->transaction(function () use ($connection, $sourcePersonId, $targetPersonId, $type, $description): PersonRelationship {
                $relationship = (new PersonRelationship)->forTenantConnection($connection);
                $relationship->fill([
                    'idSourcePerson' => $sourcePersonId,
                    'idTargetPerson' => $targetPersonId,
                    'relationshipType' => $type,
                    'description' => $this->description($description),
                ]);
                $relationship->save();

                return $relationship;
            });
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'uk_person_relationship_direction_type') || str_contains($message, 'personrelationship.idsourceperson')) {
                throw new PersonValidationException(['relationship' => 'duplicate']);
            }

            throw $exception;
        }
    }

    public function remove(ConnectionInterface $connection, int $relationshipId): void
    {
        $deleted = (new PersonRelationship)->forTenantConnection($connection)->newQuery()->whereKey($relationshipId)->delete();
        if ($deleted !== 1) {
            throw new PersonValidationException(['relationship' => 'not_found']);
        }
    }

    public function update(ConnectionInterface $connection, int $relationshipId, PersonRelationshipType $type, ?string $description = null): PersonRelationship
    {
        if ($this->description($description) !== null && mb_strlen($this->description($description), 'UTF-8') > 1000) {
            throw new PersonValidationException(['description' => 'invalid']);
        }
        $relationship = (new PersonRelationship)->forTenantConnection($connection)->newQuery()->find($relationshipId);
        if ($relationship === null) {
            throw new PersonValidationException(['relationship' => 'not_found']);
        }
        $relationship->fill(['relationshipType' => $type, 'description' => $this->description($description)]);
        try {
            $relationship->save();
        } catch (QueryException $exception) {
            throw new PersonValidationException(['relationship' => 'duplicate']);
        }

        return $relationship;
    }

    /** @return list<array{relationshipId: int, direction: string, relationshipType: PersonRelationshipType, otherPersonId: int, description: ?string}> */
    public function forPerson(ConnectionInterface $connection, int $personId): array
    {
        $relationships = (new PersonRelationship)->forTenantConnection($connection)->newQuery()
            ->where('idSourcePerson', $personId)->orWhere('idTargetPerson', $personId)->orderBy('id')->get();

        return $relationships->map(static function (PersonRelationship $relationship) use ($personId): array {
            $outgoing = $relationship->idSourcePerson === $personId;

            return [
                'relationshipId' => $relationship->id,
                'direction' => $outgoing ? 'OUTGOING' : 'INCOMING',
                'relationshipType' => $outgoing ? $relationship->relationshipType : $relationship->relationshipType->inverse(),
                'otherPersonId' => $outgoing ? $relationship->idTargetPerson : $relationship->idSourcePerson,
                'description' => $relationship->description,
            ];
        })->all();
    }

    private function assertValid(ConnectionInterface $connection, int $sourcePersonId, int $targetPersonId, ?string $description): void
    {
        $errors = [];
        if ($sourcePersonId < 1 || $targetPersonId < 1 || $sourcePersonId === $targetPersonId) {
            $errors['relationship'] = 'invalid_endpoints';
        }
        if ($this->description($description) !== null && mb_strlen($this->description($description), 'UTF-8') > 1000) {
            $errors['description'] = 'invalid';
        }
        $person = (new Person)->forTenantConnection($connection);
        if ($sourcePersonId > 0 && ! $person->newQuery()->whereKey($sourcePersonId)->exists()) {
            $errors['idSourcePerson'] = 'not_found';
        }
        if ($targetPersonId > 0 && ! $person->newQuery()->whereKey($targetPersonId)->exists()) {
            $errors['idTargetPerson'] = 'not_found';
        }
        if ($errors !== []) {
            throw new PersonValidationException($errors);
        }
    }

    private function description(?string $description): ?string
    {
        $description = $description === null ? null : trim($description);

        return $description === '' ? null : $description;
    }
}
