<?php

namespace App\Services\Access;

use App\Domain\Access\Challenge\AuthenticationChallengePurpose;
use App\Domain\Access\Challenge\ConsumedAuthenticationChallenge;
use App\Domain\Access\Challenge\IssuedAuthenticationChallenge;
use App\Models\AuthenticationChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthenticationChallengeService
{
    public function __construct(
        private readonly AuthenticationChallengeLifecycleService $lifecycle,
        private readonly AccessRateLimitService $rateLimits,
    ) {}

    public function issue(
        User $user,
        AuthenticationChallengePurpose $purpose,
        bool $rememberMeRequested = false,
    ): IssuedAuthenticationChallenge {
        return DB::transaction(function () use ($user, $purpose, $rememberMeRequested): IssuedAuthenticationChallenge {
            $this->lifecycle->discardForUserAndPurpose($user->id, $purpose->value);

            $linkToken = bin2hex(random_bytes(16));
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(config('access.authentication.emailChallengeLifetimeMinutes'));
            $challenge = AuthenticationChallenge::query()->create([
                'idUser' => $user->id,
                'purpose' => $purpose->value,
                'secretHash' => Hash::make($linkToken),
                'codeHash' => Hash::make($code),
                'expiresAt' => $expiresAt,
                'rememberMeRequested' => $rememberMeRequested,
            ]);

            return new IssuedAuthenticationChallenge(
                challengeId: $challenge->id,
                linkToken: $linkToken,
                code: $code,
                expiresAt: $expiresAt,
            );
        });
    }

    public function consumeByLinkToken(string $challengeId, string $linkToken): ?ConsumedAuthenticationChallenge
    {
        return $this->consume($challengeId, $this->normalizeLinkToken($linkToken));
    }

    public function consumeByCode(
        string $challengeId,
        string $code,
        string $origin = 'unknown',
    ): ?ConsumedAuthenticationChallenge {
        return $this->consume($challengeId, $this->normalizeCode($code), $origin, true);
    }

    private function consume(string $challengeId, ?string $secret, ?string $origin = null, bool $isCode = false): ?ConsumedAuthenticationChallenge
    {
        if ($secret === null) {
            return null;
        }

        return DB::transaction(function () use ($challengeId, $secret, $origin, $isCode): ?ConsumedAuthenticationChallenge {
            $challenge = AuthenticationChallenge::query()
                ->lockForUpdate()
                ->find($challengeId);

            if ($challenge === null) {
                return null;
            }

            if ($challenge->expiresAt->lessThanOrEqualTo(now())) {
                $this->lifecycle->discard($challenge);

                return null;
            }

            if ($isCode && ($origin === null || ! $this->canAttemptCode($challenge, $origin))) {
                return null;
            }

            $hash = $isCode ? $challenge->codeHash : $challenge->secretHash;
            if ($hash === null || ! Hash::check($secret, $hash)) {
                if ($isCode) {
                    $challenge->increment('failedAttempts');
                    if ($challenge->refresh()->failedAttempts >= config('access.authentication.codeMaximumAttempts')) {
                        $this->lifecycle->discard($challenge);
                    }
                }

                return null;
            }

            $consumed = new ConsumedAuthenticationChallenge(
                challengeId: $challenge->id,
                userId: $challenge->idUser,
                purpose: AuthenticationChallengePurpose::from($challenge->purpose),
                rememberMeRequested: $challenge->rememberMeRequested,
            );

            $this->lifecycle->discard($challenge);

            return $consumed;
        });
    }

    private function normalizeLinkToken(string $secret): ?string
    {
        $normalized = strtolower(str_replace(['-', ' '], '', trim($secret)));

        if (strlen($normalized) !== 32 || ! ctype_xdigit($normalized)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeCode(string $code): ?string
    {
        $normalized = trim($code);

        return preg_match('/^\d{6}$/D', $normalized) === 1 ? $normalized : null;
    }

    private function canAttemptCode(AuthenticationChallenge $challenge, string $origin): bool
    {
        if ($challenge->failedAttempts >= config('access.authentication.codeMaximumAttempts')) {
            $this->lifecycle->discard($challenge);

            return false;
        }

        return $this->rateLimits
            ->attemptCode($challenge->id, $origin, $challenge->idUser)
            ->allowed;
    }
}
