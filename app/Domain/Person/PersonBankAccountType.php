<?php

namespace App\Domain\Person;

enum PersonBankAccountType: string
{
    case CHECKING = 'CHECKING';
    case SAVINGS = 'SAVINGS';
    case INVESTMENT = 'INVESTMENT';
    case SALARY = 'SALARY';
    case OTHER = 'OTHER';
}
