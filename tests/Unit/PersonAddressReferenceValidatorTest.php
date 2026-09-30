<?php

namespace Tests\Unit;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonAddressInput;
use App\Domain\Person\PersonAddressReferenceValidator;
use App\Domain\Person\PersonAddressType;
use Tests\TestCase;

class PersonAddressReferenceValidatorTest extends TestCase
{
    public function test_it_rejects_incompatible_core_references(): void
    {
        $this->expectException(PersonValidationException::class);
        (new PersonAddressReferenceValidator)->assertCompatible(new PersonAddressInput('Casa', PersonAddressType::RESIDENTIAL, 1, true, 2, 3, 4, 'Rua'), true, false, false, false);
    }
}
