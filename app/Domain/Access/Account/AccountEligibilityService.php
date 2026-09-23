<?php

namespace App\Domain\Access\Account;

use App\Models\User;

class AccountEligibilityService
{
    public function status(User $user): AccountStatus
    {
        if ($user->emailVerifiedAt === null) {
            return AccountStatus::PendingEmailVerification;
        }

        if (blank($user->displayName)) {
            return AccountStatus::PendingDisplayName;
        }

        return AccountStatus::Active;
    }

    public function canAuthenticate(User $user): bool
    {
        return $this->status($user) === AccountStatus::Active;
    }
}
