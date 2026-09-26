<?php

namespace App\Domain\Authorization;

readonly class AuthorizationDecision
{
    public function __construct(public bool $allowed, public string $reasonCode) {}
}
