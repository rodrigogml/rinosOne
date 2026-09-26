<?php

namespace App\Services\Authorization\Resource;

use App\Domain\Authorization\Resource\AuthorizationResourceAdapter;
use App\Domain\Authorization\Resource\ResourceReference;
use LogicException;

class AuthorizationResourceRegistry
{
    /** @var array<string, AuthorizationResourceAdapter> */
    private array $adapters = [];

    public function register(AuthorizationResourceAdapter $adapter): void
    {
        $this->adapters[$adapter->typeKey()] = $adapter;
    }

    public function assertAvailable(ResourceReference $resource): void
    {
        $adapter = $this->adapterFor($resource);
        if (! $adapter->exists($resource)) {
            throw new LogicException('The authorization resource is not available.');
        }
    }

    public function adapterFor(ResourceReference $resource): AuthorizationResourceAdapter
    {
        $adapter = $this->adapters[$resource->type] ?? null;
        if ($adapter === null) {
            throw new LogicException('The authorization resource is not available.');
        }

        return $adapter;
    }
}
