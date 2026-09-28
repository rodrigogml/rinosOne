<?php

namespace App\Services\Authorization\Advanced;

/** @param list<array{id: int, version: int}> $policies */
readonly class AuthorizationPolicyEvaluation
{
    public function __construct(public bool $qualified, public array $policies) {}
}
