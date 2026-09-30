<?php

namespace App\Domain\Person;

enum PersonContactType: string
{
    case EMAIL = 'EMAIL';
    case PHONE = 'PHONE';
    case MOBILE = 'MOBILE';
    case WHATSAPP = 'WHATSAPP';
    case WEBSITE = 'WEBSITE';
    case OTHER = 'OTHER';
}
