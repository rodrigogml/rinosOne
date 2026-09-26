<?php

namespace App\Domain\Authorization;

enum AuthorizationDecisionResult: string
{
    case Allow = 'ALLOW';
    case Deny = 'DENY';
}
