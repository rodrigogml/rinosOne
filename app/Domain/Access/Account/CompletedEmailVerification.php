<?php

namespace App\Domain\Access\Account;

use App\Models\User;

final readonly class CompletedEmailVerification
{
    public function __construct(public User $user, public bool $rememberMeRequested) {}
}
