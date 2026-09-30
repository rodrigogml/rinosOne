<?php

namespace App\Services\Authorization\Administration;

use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationAuditEventDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationCatalogItemDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationContextDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationResourceShareDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationSubjectDto;

/**
 * Serializes contextual authorization projections with the public camelCase contract.
 */
final class AuthorizationAdministrationProjectionSerializer
{
    /** @return array{context: array<string, mixed>} */
    public function context(AuthorizationAdministrationContextDto $context): array
    {
        return ['context' => $context->toArray()];
    }

    /** @param list<AuthorizationAdministrationSubjectDto> $subjects @return list<array<string, mixed>> */
    public function subjects(array $subjects): array
    {
        return array_map(static fn (AuthorizationAdministrationSubjectDto $subject): array => $subject->toArray(), $subjects);
    }

    /** @param list<AuthorizationAdministrationCatalogItemDto> $items @return list<array<string, mixed>> */
    public function catalog(array $items): array
    {
        return array_map(static fn (AuthorizationAdministrationCatalogItemDto $item): array => $item->toArray(), $items);
    }

    /** @param list<AuthorizationAdministrationResourceShareDto> $shares @return list<array<string, mixed>> */
    public function shares(array $shares): array
    {
        return array_map(static fn (AuthorizationAdministrationResourceShareDto $share): array => $share->toArray(), $shares);
    }

    /** @param list<AuthorizationAdministrationAuditEventDto> $events @return list<array<string, mixed>> */
    public function auditEvents(array $events): array
    {
        return array_map(static fn (AuthorizationAdministrationAuditEventDto $event): array => $event->toArray(), $events);
    }
}
