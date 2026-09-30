<?php

namespace App\Domain\Person;

enum PersonPixKeyType: string
{
    case CPF = 'CPF';
    case CNPJ = 'CNPJ';
    case EMAIL = 'EMAIL';
    case PHONE = 'PHONE';
    case RANDOM = 'RANDOM';
}
