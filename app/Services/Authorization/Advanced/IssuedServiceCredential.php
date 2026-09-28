<?php

namespace App\Services\Authorization\Advanced;

readonly class IssuedServiceCredential
{
    public function __construct(public int $credentialId, public string $apiKey) {}
}
