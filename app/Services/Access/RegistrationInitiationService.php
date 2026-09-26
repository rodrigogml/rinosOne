<?php

namespace App\Services\Access;

use App\Domain\Access\Challenge\AuthenticationChallengePurpose;
use App\Domain\Access\Challenge\AuthenticationChallengeRequestResult;
use App\Domain\Access\Email\EmailNormalizer;
use App\Domain\Access\Security\SecurityEvent;
use App\Mail\Access\EmailVerificationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class RegistrationInitiationService
{
    public function __construct(
        private readonly AccessRateLimitService $rateLimits,
        private readonly AuthenticationChallengeService $challenges,
        private readonly SecurityEventLogger $securityEvents,
    ) {}

    public function initiate(string $email, string $displayName, string $origin, bool $rememberMeRequested): AuthenticationChallengeRequestResult
    {
        $normalizedEmail = EmailNormalizer::normalize($email);
        $existingUser = User::query()->where('email', $normalizedEmail)->first();
        $decision = $this->rateLimits->attemptEmailEmission($normalizedEmail, $origin, $existingUser?->id);

        if (! $decision->allowed) {
            $this->securityEvents->record(SecurityEvent::AuthenticationChallengeRejected, $existingUser?->id);

            return new AuthenticationChallengeRequestResult($decision, random_int(1, PHP_INT_MAX));
        }

        $challengeId = random_int(1, PHP_INT_MAX);

        DB::transaction(function () use ($normalizedEmail, $displayName, $rememberMeRequested, &$challengeId): void {
            $user = User::query()->firstOrCreate(['email' => $normalizedEmail], ['displayName' => trim($displayName)]);

            if ($user->emailVerifiedAt !== null) {
                return;
            }

            $user->forceFill(['displayName' => trim($displayName)])->save();

            $issued = $this->challenges->issue(
                $user,
                AuthenticationChallengePurpose::EmailVerification,
                $rememberMeRequested,
            );
            $challengeId = $issued->challengeId;

            Mail::to($user->email)->queue(new EmailVerificationMessage(
                $issued->challengeId,
                $issued->linkToken,
                $issued->code,
            ));
            $this->securityEvents->record(SecurityEvent::AuthenticationChallengeRequested, $user->id);
        });

        return new AuthenticationChallengeRequestResult($decision, $challengeId);
    }
}
