<?php

namespace App\Services\Authorization\Administration\Dto;

use InvalidArgumentException;

/**
 * Contextual relation to a workspace resource; it deliberately contains no item ownership field.
 */
final readonly class AuthorizationAdministrationResourceShareDto
{
    /** @param array{resourceType: string, resourceId: int}|null $inheritedFrom */
    public function __construct(
        public int $id,
        public string $resourceType,
        public int $resourceId,
        public AuthorizationAdministrationSubjectDto $grantee,
        public string $relation,
        public string $origin,
        public ?array $inheritedFrom = null,
    ) {
        if ($id < 1
            || $resourceId < 1
            || trim($resourceType) === ''
            || ! in_array($relation, ['READ', 'EDIT', 'ADMIN'], true)
            || ! in_array($origin, ['DIRECT', 'INHERITED'], true)
            || ($origin === 'DIRECT' && $inheritedFrom !== null)
            || ($origin === 'INHERITED' && ($inheritedFrom === null || ! is_string($inheritedFrom['resourceType'] ?? null) || trim($inheritedFrom['resourceType']) === '' || ! is_int($inheritedFrom['resourceId'] ?? null) || $inheritedFrom['resourceId'] < 1))) {
            throw new InvalidArgumentException('Invalid authorization resource share projection.');
        }
    }

    /** @return array{id: int, resourceType: string, resourceId: int, grantee: array<string, mixed>, relation: string, origin: string, inheritedFrom: ?array{resourceType: string, resourceId: int}} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'resourceType' => $this->resourceType,
            'resourceId' => $this->resourceId,
            'grantee' => $this->grantee->toArray(),
            'relation' => $this->relation,
            'origin' => $this->origin,
            'inheritedFrom' => $this->inheritedFrom,
        ];
    }
}
