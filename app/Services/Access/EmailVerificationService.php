<?php

namespace App\Services\Access;

use App\Domain\Access\Account\CompletedEmailVerification;
use App\Domain\Access\Challenge\AuthenticationChallengePurpose;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EmailVerificationService
{
    public function __construct(private readonly AuthenticationChallengeService $challenges) {}

    public function confirmByCode(string $challengeId, string $code, string $displayName, string $origin): ?CompletedEmailVerification
    {
        return $this->confirm($this->challenges->consumeByCode($challengeId, $code, $origin), $displayName);
    }

    public function confirmByLink(string $challengeId, string $token, string $displayName): ?CompletedEmailVerification
    {
        return $this->confirm($this->challenges->consumeByLinkToken($challengeId, $token), $displayName);
    }

    private function confirm(?object $consumed, string $displayName): ?CompletedEmailVerification
    {
        if ($consumed === null || $consumed->purpose !== AuthenticationChallengePurpose::EmailVerification) {
            return null;
        }

        return DB::transaction(function () use ($consumed, $displayName): CompletedEmailVerification {
            $user = User::query()->lockForUpdate()->findOrFail($consumed->userId);
            $user->forceFill(['emailVerifiedAt' => now(), 'displayName' => trim($displayName)])->save();

            return new CompletedEmailVerification($user, $consumed->rememberMeRequested);
        });
    }
}
