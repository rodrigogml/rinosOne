<?php

namespace App\Domain\Access\Account;

enum AccountStatus: string
{
    case PendingEmailVerification = 'pending_email_verification';
    case PendingDisplayName = 'pending_display_name';
    case Active = 'active';
}
