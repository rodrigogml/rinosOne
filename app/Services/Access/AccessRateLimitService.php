<?php

namespace App\Services\Access;

use App\Domain\Access\Email\EmailNormalizer;
use App\Domain\Access\RateLimiting\AccessRateLimitDecision;
use Illuminate\Cache\RateLimiter;

final class AccessRateLimitService
{
    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function attemptEmailEmission(string $email, string $origin, ?string $userId = null): AccessRateLimitDecision
    {
        $scopes = [
            $this->scopeInSeconds(
                'email-emission-cooldown',
                EmailNormalizer::normalize($email),
                1,
                config('access.authentication.emailResendCooldownSeconds'),
                false,
            ),
            $this->scope(
                'email-emission-email',
                EmailNormalizer::normalize($email),
                config('access.authentication.emailEmissionLimit'),
                config('access.authentication.emailEmissionWindowMinutes'),
            ),
            $this->scope(
                'email-emission-origin',
                $origin,
                config('access.authentication.originEmissionLimit'),
                config('access.authentication.originEmissionWindowMinutes'),
            ),
        ];

        if ($userId !== null) {
            $scopes[] = $this->scope(
                'email-emission-user',
                $userId,
                config('access.authentication.userEmissionLimit'),
                config('access.authentication.userEmissionWindowMinutes'),
            );
        }

        return $this->attempt($scopes);
    }

    public function attemptCode(string $challengeId, string $origin, ?string $userId = null): AccessRateLimitDecision
    {
        $scopes = [
            $this->scope(
                'code-challenge',
                $challengeId,
                config('access.authentication.codeAttemptLimit'),
                config('access.authentication.codeAttemptWindowMinutes'),
            ),
            $this->scope(
                'code-origin',
                $origin,
                config('access.authentication.codeAttemptLimit'),
                config('access.authentication.codeAttemptWindowMinutes'),
            ),
        ];

        if ($userId !== null) {
            $scopes[] = $this->scope(
                'code-user',
                $userId,
                config('access.authentication.codeAttemptLimit'),
                config('access.authentication.codeAttemptWindowMinutes'),
            );
        }

        return $this->attempt($scopes);
    }

    public function attemptPassword(string $email, string $origin, ?string $userId = null): AccessRateLimitDecision
    {
        $scopes = [
            $this->scope(
                'password-email',
                EmailNormalizer::normalize($email),
                config('access.authentication.passwordAttemptLimit'),
                config('access.authentication.passwordAttemptWindowMinutes'),
            ),
            $this->scope(
                'password-origin',
                $origin,
                config('access.authentication.passwordAttemptLimit'),
                config('access.authentication.passwordAttemptWindowMinutes'),
            ),
        ];

        if ($userId !== null) {
            $scopes[] = $this->scope(
                'password-user',
                $userId,
                config('access.authentication.userPasswordAttemptLimit'),
                config('access.authentication.userPasswordAttemptWindowMinutes'),
            );
        }

        return $this->attempt($scopes);
    }

    /**
     * @param  array<int, array{key: string, limit: int, windowSeconds: int, blockOnLimit: bool}>  $scopes
     */
    private function attempt(array $scopes): AccessRateLimitDecision
    {
        foreach ($scopes as $scope) {
            $blockKey = $scope['key'].':block';

            if ($this->rateLimiter->tooManyAttempts($blockKey, 1)) {
                return AccessRateLimitDecision::deny($this->rateLimiter->availableIn($blockKey));
            }

            if ($this->rateLimiter->tooManyAttempts($scope['key'], $scope['limit'])) {
                if (! $scope['blockOnLimit']) {
                    return AccessRateLimitDecision::deny($this->rateLimiter->availableIn($scope['key']));
                }
                $this->rateLimiter->hit($blockKey, $this->temporaryBlockSeconds());

                return AccessRateLimitDecision::deny($this->temporaryBlockSeconds());
            }
        }

        foreach ($scopes as $scope) {
            $this->rateLimiter->hit($scope['key'], $scope['windowSeconds']);
        }

        return AccessRateLimitDecision::allow();
    }

    /**
     * @return array{key: string, limit: int, windowSeconds: int, blockOnLimit: bool}
     */
    private function scope(string $name, string $identifier, mixed $limit, mixed $windowMinutes): array
    {
        return $this->scopeInSeconds($name, $identifier, $limit, max(1, (int) $windowMinutes) * 60);
    }

    /**
     * @return array{key: string, limit: int, windowSeconds: int, blockOnLimit: bool}
     */
    private function scopeInSeconds(string $name, string $identifier, mixed $limit, mixed $windowSeconds, bool $blockOnLimit = true): array
    {
        $normalizedIdentifier = mb_strtolower(trim($identifier), 'UTF-8');
        $fingerprint = hash_hmac('sha256', $name.':'.$normalizedIdentifier, (string) config('app.key'));

        return [
            'key' => 'access-rate-limit:'.$name.':'.$fingerprint,
            'limit' => max(1, (int) $limit),
            'windowSeconds' => max(1, (int) $windowSeconds),
            'blockOnLimit' => $blockOnLimit,
        ];
    }

    private function temporaryBlockSeconds(): int
    {
        return max(1, (int) config('access.authentication.temporaryBlockMinutes')) * 60;
    }
}
