<?php

namespace App\Services\Authorization\Administration\Dto;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Safe audit projection that carries identifiers and labels but never before/after snapshots.
 */
final readonly class AuthorizationAdministrationAuditEventDto
{
    public function __construct(
        public int $id,
        public DateTimeInterface $occurredAt,
        public ?int $actorUserId,
        public string $operation,
        public string $targetType,
        public int $targetId,
    ) {
        if ($id < 1
            || ($actorUserId !== null && $actorUserId < 1)
            || $targetId < 1
            || trim($operation) === ''
            || trim($targetType) === '') {
            throw new InvalidArgumentException('Invalid authorization audit event projection.');
        }
    }

    /** @return array{id: int, occurredAt: string, actorUserId: ?int, operation: string, targetType: string, targetId: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
            'actorUserId' => $this->actorUserId,
            'operation' => $this->operation,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
        ];
    }
}
