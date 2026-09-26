<?php

namespace App\Services\Authorization\Resource;

use App\Domain\Authorization\Resource\ResourceReference;
use App\Models\AuthorizationResourceType;

class AuthorizationResourceTypeCatalog
{
    public function __construct(private readonly AuthorizationResourceRegistry $registry) {}

    public function resolve(ResourceReference $resource): AuthorizationResourceType
    {
        $this->registry->assertAvailable($resource);

        return AuthorizationResourceType::query()->firstOrCreate(
            ['key' => $resource->type],
            ['scope' => $resource->scope->value, 'active' => true],
        );
    }
}
