<?php

namespace App\Domain\Authorization\Resource;

use App\Domain\Authorization\AuthorizationScope;
use InvalidArgumentException;

readonly class ResourceReference
{
    public function __construct(public string $type, public int $id, public AuthorizationScope $scope, public ?int $tenantId = null)
    {
        if (trim($type) === '' || $id < 1 || ($scope === AuthorizationScope::Tenant) !== ($tenantId !== null)) {
            throw new InvalidArgumentException('The authorization resource reference is invalid.');
        }
    }
}
