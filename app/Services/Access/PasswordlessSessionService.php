<?php

namespace App\Services\Access;

use App\Domain\Access\Account\AccountEligibilityService;
use App\Domain\Access\Challenge\AuthenticationChallengePurpose;
use App\Domain\Access\Challenge\AuthenticationChallengeRequestResult;
use App\Domain\Access\Email\EmailNormalizer;
use App\Mail\Access\PasswordlessLoginMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class PasswordlessSessionService
{
    public function __construct(private readonly AccessRateLimitService $limits, private readonly AuthenticationChallengeService $challenges, private readonly AccountEligibilityService $eligibility) {}

    public function request(string $email, string $origin, bool $remember): AuthenticationChallengeRequestResult
    {
        $email = EmailNormalizer::normalize($email);
        $user = User::query()->where('email', $email)->first();
        $decision = $this->limits->attemptEmailEmission($email, $origin, $user?->id);
        if (! $decision->allowed) {
            return new AuthenticationChallengeRequestResult($decision, random_int(1, PHP_INT_MAX));
        }

        if ($user === null || ! $this->eligibility->canAuthenticate($user)) {
            return new AuthenticationChallengeRequestResult($decision, random_int(1, PHP_INT_MAX));
        }

        $issued = $this->challenges->issue($user, AuthenticationChallengePurpose::PasswordlessLogin, $remember);
        Mail::to($user->email)->queue(new PasswordlessLoginMessage($issued->challengeId, $issued->linkToken, $issued->code));

        return new AuthenticationChallengeRequestResult($decision, $issued->challengeId);
    }

    public function confirmCode(int $id, string $code, string $origin): ?array
    {
        return $this->confirm($this->challenges->consumeByCode($id, $code, $origin));
    }

    public function confirmLink(int $id, string $token): ?array
    {
        return $this->confirm($this->challenges->consumeByLinkToken($id, $token));
    }

    private function confirm(?object $consumed): ?array
    {
        if ($consumed === null || $consumed->purpose !== AuthenticationChallengePurpose::PasswordlessLogin) {
            return null;
        } $user = User::query()->find($consumed->userId);

        return $user !== null && $this->eligibility->canAuthenticate($user) ? [$user, $consumed->rememberMeRequested] : null;
    }
}
