<?php

namespace App\Domain\Person;

readonly class PersonContactInput
{
    public function __construct(public PersonContactType $type, public string $value, public ?string $description = null) {}
}
