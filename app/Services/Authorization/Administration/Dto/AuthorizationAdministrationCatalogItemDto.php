<?php

namespace App\Services\Authorization\Administration\Dto;

use InvalidArgumentException;

/**
 * Common catalog projection for contextual roles, groups and permissions.
 */
final readonly class AuthorizationAdministrationCatalogItemDto
{
    public function __construct(
        public int $id,
        public string $catalogType,
        public string $key,
        public string $displayName,
        public string $description,
        public string $scope,
        public bool $systemManaged,
        public bool $active,
    ) {
        if ($id < 1
            || ! in_array($catalogType, ['ROLE', 'GROUP', 'PERMISSION'], true)
            || ! in_array($scope, ['PERSONAL', 'TENANT', 'PLATFORM'], true)
            || trim($key) === ''
            || trim($displayName) === '') {
            throw new InvalidArgumentException('Invalid authorization catalog item projection.');
        }
    }

    /** @return array{id: int, catalogType: string, key: string, displayName: string, description: string, scope: string, systemManaged: bool, active: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'catalogType' => $this->catalogType,
            'key' => $this->key,
            'displayName' => $this->displayName,
            'description' => $this->description,
            'scope' => $this->scope,
            'systemManaged' => $this->systemManaged,
            'active' => $this->active,
        ];
    }
}
