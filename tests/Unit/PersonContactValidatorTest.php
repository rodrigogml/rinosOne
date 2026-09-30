<?php

namespace Tests\Unit;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonContactInput;
use App\Domain\Person\PersonContactType;
use App\Domain\Person\PersonContactValidator;
use Tests\TestCase;

class PersonContactValidatorTest extends TestCase
{
    public function test_it_normalizes_typed_contacts_without_declaring_a_primary_contact(): void
    {
        $email = (new PersonContactValidator)->validate(new PersonContactInput(PersonContactType::EMAIL, ' User@Example.test '));
        $phone = (new PersonContactValidator)->validate(new PersonContactInput(PersonContactType::MOBILE, '+55 (11) 99999-9999'));

        $this->assertSame('user@example.test', $email['normalizedValue']);
        $this->assertSame('5511999999999', $phone['normalizedValue']);
    }

    public function test_it_rejects_an_invalid_value_for_its_contact_type(): void
    {
        $this->expectException(PersonValidationException::class);
        (new PersonContactValidator)->validate(new PersonContactInput(PersonContactType::EMAIL, 'not-an-email'));
    }
}
