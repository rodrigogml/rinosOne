<?php

namespace App\Domain\Authorization\Resource;

interface AuthorizationResourceAdapter
{
    public function typeKey(): string;

    public function exists(ResourceReference $resource): bool;

    /** @return list<string> */
    public function supportedActions(): array;

    /** @return list<string> */
    public function supportedRelations(): array;

    /** Declares whether a resource relation may be granted to a group. */
    public function allowsGroupRelations(): bool;

    /** @return list<int> IDs do recurso avaliado e de seus ancestrais autorizáveis. */
    public function inheritedResourceIds(ResourceReference $resource): array;

    public function isWorkspacePrincipal(ResourceReference $resource, int $userId): bool;
}
