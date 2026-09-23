<?php

namespace App\Domain\Access\Challenge;

enum AuthenticationChallengePurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordlessLogin = 'passwordless_login';
}
