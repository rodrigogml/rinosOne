<?php

namespace Tests\Unit;

use App\Domain\Person\Exception\PersonValidationException;
use App\Domain\Person\PersonPixKeyInput;
use App\Domain\Person\PersonPixKeyType;
use App\Domain\Person\PersonPixKeyValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonPixKeyValidatorTest extends TestCase
{
    public function test_it_normalizes_email_and_phone_keys(): void
    {
        $validator = new PersonPixKeyValidator;
        $this->assertSame('user@example.test', $validator->validate(new PersonPixKeyInput(PersonPixKeyType::EMAIL, 'User@example.test'))['normalizedKeyValue']);
        $this->assertSame('5511999999999', $validator->validate(new PersonPixKeyInput(PersonPixKeyType::PHONE, '+55 11 99999-9999'))['normalizedKeyValue']);
    }

    #[DataProvider('invalidKeyValues')]
    public function test_it_rejects_values_that_do_not_match_the_selected_key_type(PersonPixKeyType $type, string $value): void
    {
        $this->expectException(PersonValidationException::class);
        (new PersonPixKeyValidator)->validate(new PersonPixKeyInput($type, $value));
    }

    /** @return array<string, array{PersonPixKeyType, string}> */
    public static function invalidKeyValues(): array
    {
        return [
            'email' => [PersonPixKeyType::EMAIL, 'not-an-email'],
            'phone' => [PersonPixKeyType::PHONE, '123'],
            'cpf' => [PersonPixKeyType::CPF, '123'],
            'cnpj' => [PersonPixKeyType::CNPJ, '123'],
            'random' => [PersonPixKeyType::RANDOM, 'not-a-random-key'],
        ];
    }
}
