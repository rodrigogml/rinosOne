<?php

namespace Tests\Unit;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonAddressInput;
use App\Domain\Person\PersonAddressType;
use App\Domain\Person\PersonAddressValidator;
use Tests\TestCase;

class PersonAddressValidatorTest extends TestCase
{
    public function test_it_accepts_a_free_brazilian_street_when_territory_is_linked(): void
    {
        $address = (new PersonAddressValidator)->validate(new PersonAddressInput('Residência', PersonAddressType::RESIDENTIAL, 1, true, 2, 3, null, ' Rua ainda não catalogada ', '12A', '01001-000'));

        $this->assertSame('Rua ainda não catalogada', $address['street']);
        $this->assertSame('12A', $address['number']);
    }

    public function test_it_requires_brazilian_state_and_municipality_but_not_for_international_addresses(): void
    {
        (new PersonAddressValidator)->validate(new PersonAddressInput('Office', PersonAddressType::COMMERCIAL, 55, false, null, null, null, 'Main Street'));

        $this->expectException(PersonValidationException::class);
        (new PersonAddressValidator)->validate(new PersonAddressInput('Casa', PersonAddressType::RESIDENTIAL, 1, true, null, null, null, 'Rua'));
    }

    public function test_it_preserves_final_address_fields_and_uses_textual_state_and_city_only_outside_brazil(): void
    {
        $address = (new PersonAddressValidator)->validate(new PersonAddressInput(
            'Office',
            PersonAddressType::COMMERCIAL,
            55,
            false,
            null,
            null,
            null,
            'Main Street',
            '12A',
            'SW1A 1AA',
            'London',
            'London',
            'Floor 2',
            'Westminster',
            'Near the park',
        ));

        $this->assertSame('London', $address['stateText']);
        $this->assertSame('London', $address['cityText']);
        $this->assertSame('Floor 2', $address['complement']);
        $this->assertSame('Westminster', $address['district']);
        $this->assertSame('Near the park', $address['reference']);
    }
}
