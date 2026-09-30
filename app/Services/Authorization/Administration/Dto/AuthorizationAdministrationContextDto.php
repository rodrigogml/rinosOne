<?php

namespace App\Services\Authorization\Administration\Dto;

use App\Services\Authorization\Administration\AuthorizationAdministrationContext;
use InvalidArgumentException;

/**
 * Read-only contextual projection for the authorization administration UI.
 */
final readonly class AuthorizationAdministrationContextDto
{
    public function __construct(
        public string $scope,
        public ?int $tenantId,
        public string $displayName,
        public ?string $workspaceKind,
        public bool $canReadAccess,
        public bool $canManageRoles,
        public bool $canManageSharing,
        public bool $canUseAdvancedControls,
    ) {
        if (! in_array($scope, ['PERSONAL', 'TENANT', 'PLATFORM'], true)
            || ($scope === 'TENANT' && ($tenantId === null || $tenantId < 1))
            || ($scope !== 'TENANT' && $tenantId !== null)
            || trim($displayName) === '') {
            throw new InvalidArgumentException('Invalid authorization administration context projection.');
        }
    }

    public static function fromContext(
        AuthorizationAdministrationContext $context,
        string $displayName,
        ?string $workspaceKind,
    ): self {
        return new self(
            $context->scope->value,
            $context->tenantId,
            $displayName,
            $workspaceKind,
            $context->capabilities->canReadAccess,
            $context->capabilities->canManageRoles,
            $context->capabilities->canManageSharing,
            $context->capabilities->canUseAdvancedControls,
        );
    }

    /** @return array{scope: string, tenantId: ?int, displayName: string, workspaceKind: ?string, capabilities: array{canReadAccess: bool, canManageRoles: bool, canManageSharing: bool, canUseAdvancedControls: bool}} */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'tenantId' => $this->tenantId,
            'displayName' => $this->displayName,
            'workspaceKind' => $this->workspaceKind,
            'capabilities' => [
                'canReadAccess' => $this->canReadAccess,
                'canManageRoles' => $this->canManageRoles,
                'canManageSharing' => $this->canManageSharing,
                'canUseAdvancedControls' => $this->canUseAdvancedControls,
            ],
        ];
    }
}
