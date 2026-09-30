<?php

namespace Tests\Unit;

use App\Domain\Authorization\AuthorizationScope;
use App\Services\Authorization\Administration\AuthorizationAdministrationCapabilities;
use App\Services\Authorization\Administration\AuthorizationAdministrationContext;
use App\Services\Authorization\Administration\AuthorizationAdministrationProjectionSerializer;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationAccessSourceDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationAuditEventDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationCatalogItemDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationContextDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationResourceShareDto;
use App\Services\Authorization\Administration\Dto\AuthorizationAdministrationSubjectDto;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class AuthorizationAdministrationProjectionSerializerTest extends TestCase
{
    public function test_it_serializes_contextual_projections_with_the_public_camel_case_shape(): void
    {
        $serializer = new AuthorizationAdministrationProjectionSerializer;
        $source = new AuthorizationAdministrationAccessSourceDto('ROLE', 'Administradores', 'TENANT', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $subject = new AuthorizationAdministrationSubjectDto(7, 'USER', 'Ana', [$source], ['tenant.authorization.read']);

        $context = AuthorizationAdministrationContextDto::fromContext(
            new AuthorizationAdministrationContext(AuthorizationScope::Tenant, 42, new AuthorizationAdministrationCapabilities(true, true, true, true)),
            'Empresa Exemplo',
            'TENANT',
        );

        $this->assertSame([
            'context' => [
                'scope' => 'TENANT', 'tenantId' => 42, 'displayName' => 'Empresa Exemplo', 'workspaceKind' => 'TENANT',
                'capabilities' => ['canReadAccess' => true, 'canManageRoles' => true, 'canManageSharing' => true, 'canUseAdvancedControls' => true],
            ],
        ], $serializer->context($context));
        $this->assertSame('ROLE', $serializer->subjects([$subject])[0]['accessSources'][0]['type']);
        $this->assertSame('tenant.authorization.read', $serializer->subjects([$subject])[0]['effectiveCapabilities'][0]);
        $this->assertSame('PERMISSION', $serializer->catalog([new AuthorizationAdministrationCatalogItemDto(2, 'PERMISSION', 'tenant.authorization.read', 'Ler acessos', '', 'TENANT', true, true)])[0]['catalogType']);
        $this->assertSame('INHERITED', $serializer->shares([new AuthorizationAdministrationResourceShareDto(3, 'FOLDER', 9, $subject, 'EDIT', 'INHERITED', ['resourceType' => 'FOLDER', 'resourceId' => 8])])[0]['origin']);
        $this->assertArrayNotHasKey('before', $serializer->auditEvents([new AuthorizationAdministrationAuditEventDto(4, new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 7, 'ROLE_ASSIGNED', 'ROLE', 2)])[0]);
    }

    public function test_it_rejects_invalid_contextual_projection_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuthorizationAdministrationResourceShareDto(
            3,
            'FOLDER',
            9,
            new AuthorizationAdministrationSubjectDto(7, 'USER', 'Ana'),
            'EDIT',
            'DIRECT',
            ['resourceType' => 'FOLDER', 'resourceId' => 8],
        );
    }
}
