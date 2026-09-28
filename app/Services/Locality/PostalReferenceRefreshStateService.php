<?php

namespace App\Services\Locality;

use Illuminate\Support\Facades\Cache;

final class PostalReferenceRefreshStateService
{
    public const PENDING = 'PENDING';

    public const COMPLETED = 'COMPLETED';

    public const COMPLETED_WITH_ERRORS = 'COMPLETED_WITH_ERRORS';

    /**
     * Mark a refresh as pending when no equivalent refresh is already active.
     */
    public function start(string $countryCode, string $normalizedPostalCode): bool
    {
        if (! Cache::add($this->pendingKey($countryCode, $normalizedPostalCode), true, $this->ttlSeconds())) {
            return false;
        }

        Cache::put($this->stateKey($countryCode, $normalizedPostalCode), self::PENDING, $this->ttlSeconds());

        return true;
    }

    public function state(string $countryCode, string $normalizedPostalCode): PostalReferenceRefreshState
    {
        $state = Cache::get($this->stateKey($countryCode, $normalizedPostalCode));

        if (! in_array($state, [self::PENDING, self::COMPLETED, self::COMPLETED_WITH_ERRORS], true)) {
            return new PostalReferenceRefreshState(self::COMPLETED, null);
        }

        return new PostalReferenceRefreshState(
            $state,
            $state === self::PENDING ? $this->pollAfterMilliseconds() : null,
        );
    }

    public function complete(string $countryCode, string $normalizedPostalCode, bool $withErrors): void
    {
        Cache::forget($this->pendingKey($countryCode, $normalizedPostalCode));
        Cache::put(
            $this->stateKey($countryCode, $normalizedPostalCode),
            $withErrors ? self::COMPLETED_WITH_ERRORS : self::COMPLETED,
            $this->ttlSeconds(),
        );
    }

    public function lockKey(string $countryCode, string $normalizedPostalCode): string
    {
        return 'locality.postal-reference-enrichment.'.strtoupper($countryCode).'.'.$normalizedPostalCode;
    }

    private function stateKey(string $countryCode, string $normalizedPostalCode): string
    {
        return 'locality.postal-reference-refresh-state.'.strtoupper($countryCode).'.'.$normalizedPostalCode;
    }

    private function pendingKey(string $countryCode, string $normalizedPostalCode): string
    {
        return 'locality.postal-reference-refresh-pending.'.strtoupper($countryCode).'.'.$normalizedPostalCode;
    }

    private function ttlSeconds(): int
    {
        return max(1, (int) config('localities.postal.refresh_state_ttl_seconds'));
    }

    private function pollAfterMilliseconds(): int
    {
        return max(1, (int) config('localities.postal.poll_after_milliseconds'));
    }
}
