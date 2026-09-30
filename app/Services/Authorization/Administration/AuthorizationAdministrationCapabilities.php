<?php

namespace App\Services\Authorization\Administration;

/**
 * Describes the administrative actions that may be presented for one resolved context.
 *
 * The values support composition of an interaction surface only. Every read and command
 * remains subject to an authorization decision at its server-side boundary.
 */
readonly class AuthorizationAdministrationCapabilities
{
    public function __construct(
        public bool $canReadAccess,
        public bool $canManageRoles,
        public bool $canManageSharing,
        public bool $canUseAdvancedControls,
    ) {}
}
