<?php

namespace App\Services\Authorization\Administration\Dto;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Explains one non-sensitive source contributing to a subject's effective access.
 */
final readonly class AuthorizationAdministrationAccessSourceDto
{
    public function __construct(
        public string $type,
        public string $displayName,
        public string $scope,
        public ?DateTimeInterface $expiresAt = null,
        /** @var array{resourceType: string, resourceId: int}|null */
        public ?array $resource = null,
    ) {
        if (! in_array($type, ['ROLE', 'GROUP', 'DIRECT_GRANT', 'SHARE', 'DELEGATION', 'POLICY'], true)
            || ! in_array($scope, ['PERSONAL', 'TENANT', 'PLATFORM'], true)
            || trim($displayName) === ''
            || ($resource !== null && (! is_string($resource['resourceType'] ?? null) || trim($resource['resourceType']) === '' || ! is_int($resource['resourceId'] ?? null) || $resource['resourceId'] < 1))) {
            throw new InvalidArgumentException('Invalid authorization access source projection.');
        }
    }

    /** @return array{type: string, displayName: string, scope: string, expiresAt: ?string, resource: ?array{resourceType: string, resourceId: int}} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'displayName' => $this->displayName,
            'scope' => $this->scope,
            'expiresAt' => $this->expiresAt?->format(DATE_ATOM),
            'resource' => $this->resource,
        ];
    }
}
