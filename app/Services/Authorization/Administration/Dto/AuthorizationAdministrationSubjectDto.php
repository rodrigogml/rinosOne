<?php

namespace App\Services\Authorization\Administration\Dto;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Projection of an administrable human or service identity in one authorization context.
 */
final readonly class AuthorizationAdministrationSubjectDto
{
    /** @param list<AuthorizationAdministrationAccessSourceDto> $accessSources @param list<string> $effectiveCapabilities */
    public function __construct(
        public int $subjectId,
        public string $subjectType,
        public string $displayName,
        public array $accessSources = [],
        public array $effectiveCapabilities = [],
        public ?DateTimeInterface $expiresAt = null,
    ) {
        if ($subjectId < 1
            || ! in_array($subjectType, ['USER', 'SERVICE_IDENTITY', 'GROUP'], true)
            || trim($displayName) === '') {
            throw new InvalidArgumentException('Invalid authorization subject projection.');
        }

        foreach ($accessSources as $source) {
            if (! $source instanceof AuthorizationAdministrationAccessSourceDto) {
                throw new InvalidArgumentException('Invalid authorization subject projection.');
            }
        }

        foreach ($effectiveCapabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                throw new InvalidArgumentException('Invalid authorization subject projection.');
            }
        }
    }

    /** @return array{subjectId: int, subjectType: string, displayName: string, accessSources: list<array{type: string, displayName: string, scope: string, expiresAt: ?string}>, effectiveCapabilities: list<string>, expiresAt: ?string} */
    public function toArray(): array
    {
        return [
            'subjectId' => $this->subjectId,
            'subjectType' => $this->subjectType,
            'displayName' => $this->displayName,
            'accessSources' => array_map(static fn (AuthorizationAdministrationAccessSourceDto $source): array => $source->toArray(), $this->accessSources),
            'effectiveCapabilities' => $this->effectiveCapabilities,
            'expiresAt' => $this->expiresAt?->format(DATE_ATOM),
        ];
    }
}
