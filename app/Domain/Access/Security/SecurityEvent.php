<?php

namespace App\Domain\Access\Security;

enum SecurityEvent: string
{
    case AuthenticationChallengeRequested = 'authentication_challenge_requested';
    case AuthenticationChallengeRejected = 'authentication_challenge_rejected';
    case SessionInvalidated = 'session_invalidated';
    case PersistentAuthenticationRevoked = 'persistent_authentication_revoked';
    case AuthenticationSucceeded = 'authentication_succeeded';
}
