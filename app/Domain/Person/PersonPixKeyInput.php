<?php

namespace App\Domain\Person;

readonly class PersonPixKeyInput
{
    public function __construct(public PersonPixKeyType $type, public string $value, public PersonStatus $status = PersonStatus::ACTIVE) {}
}
