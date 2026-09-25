<?php

namespace App\Services\Access;

use App\Domain\Access\Account\CompletedEmailVerification;
use App\Domain\Access\Challenge\AuthenticationChallengePurpose;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EmailVerificationService
{
    public function __construct(private readonly AuthenticationChallengeService $challenges) {}

    public function confirmByCode(string $challengeId, string $code, string $origin): ?CompletedEmailVerification
    {
        return $this->confirm($this->challenges->consumeByCode($challengeId, $code, $origin));
    }

    public function confirmByLink(string $challengeId, string $token): ?CompletedEmailVerification
    {
        return $this->confirm($this->challenges->consumeByLinkToken($challengeId, $token));
    }

    private function confirm(?object $consumed): ?CompletedEmailVerification
    {
        if ($consumed === null || $consumed->purpose !== AuthenticationChallengePurpose::EmailVerification) {
            return null;
        }

        return DB::transaction(function () use ($consumed): ?CompletedEmailVerification {
            $user = User::query()->lockForUpdate()->findOrFail($consumed->userId);
            if (trim((string) $user->displayName) === '') {
                return null;
            }
            $user->forceFill(['emailVerifiedAt' => now()])->save();

            return new CompletedEmailVerification($user, $consumed->rememberMeRequested);
        });
    }
}
