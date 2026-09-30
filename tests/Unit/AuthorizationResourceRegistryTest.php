<?php

namespace Tests\Unit;

use App\Domain\Authorization\AuthorizationScope;
use App\Domain\Authorization\Resource\AuthorizationResourceAdapter;
use App\Domain\Authorization\Resource\ResourceReference;
use App\Services\Authorization\Resource\AuthorizationResourceRegistry;
use LogicException;
use Tests\TestCase;

class AuthorizationResourceRegistryTest extends TestCase
{
    public function test_it_resolves_only_a_registered_available_resource(): void
    {
        $registry = new AuthorizationResourceRegistry;
        $registry->register(new class implements AuthorizationResourceAdapter
        {
            public function typeKey(): string
            {
                return 'personal.folder';
            }

            public function exists(ResourceReference $resource): bool
            {
                return $resource->id === 7;
            }

            public function supportedActions(): array
            {
                return ['personal.folder.read', 'personal.folder.edit'];
            }

            public function supportedRelations(): array
            {
                return ['READ', 'EDIT'];
            }

            public function allowsGroupRelations(): bool
            {
                return true;
            }

            public function inheritedResourceIds(ResourceReference $resource): array
            {
                return [$resource->id];
            }

            public function isWorkspacePrincipal(ResourceReference $resource, int $userId): bool
            {
                return false;
            }
        });

        $registry->assertAvailable(new ResourceReference('personal.folder', 7, AuthorizationScope::Personal));

        $this->expectException(LogicException::class);
        $registry->assertAvailable(new ResourceReference('personal.folder', 8, AuthorizationScope::Personal));
    }
}
