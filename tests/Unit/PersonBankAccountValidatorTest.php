<?php

namespace Tests\Unit;

use App\Domain\Person\PersonBankAccountInput;
use App\Domain\Person\PersonBankAccountType;
use App\Domain\Person\PersonBankAccountValidator;
use Tests\TestCase;

class PersonBankAccountValidatorTest extends TestCase
{
    public function test_it_preserves_bank_values_as_text(): void
    {
        $account = (new PersonBankAccountValidator)->validate(new PersonBankAccountInput('Conta salário', PersonBankAccountType::SALARY, null, '0012', 'X', '000123', '9'));
        $this->assertSame('0012', $account['agency']);
        $this->assertSame('000123', $account['accountNumber']);
    }
}
