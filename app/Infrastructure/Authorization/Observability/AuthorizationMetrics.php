<?php

namespace App\Infrastructure\Authorization\Observability;

use Illuminate\Support\Facades\Cache;

/**
 * Records bounded authorization counters without principal, permission, tenant
 * or resource labels. Metric failures are intentionally non-blocking.
 */
class AuthorizationMetrics
{
    private const PREFIX = 'authorization:metrics:';

    public function recordDecision(bool $allowed, int $durationMilliseconds, bool $cacheHit): void
    {
        $this->increment($allowed ? 'decision:allow' : 'decision:deny');
        $this->increment($cacheHit ? 'cache:hit' : 'cache:miss');
        $this->increment('duration:totalMilliseconds', max(0, $durationMilliseconds));
        $this->increment('duration:count');
    }

    public function recordBatch(int $size): void
    {
        $this->increment('batch:count');
        $this->increment('batch:sizeTotal', max(0, $size));
    }

    public function recordFailure(): void
    {
        $this->increment('failure:count');
    }

    /** @return array<string, int> */
    public function snapshot(): array
    {
        return collect([
            'decision:allow', 'decision:deny', 'cache:hit', 'cache:miss',
            'duration:totalMilliseconds', 'duration:count', 'batch:count',
            'batch:sizeTotal', 'failure:count',
        ])->mapWithKeys(fn (string $name): array => [$name => (int) Cache::get(self::PREFIX.$name, 0)])->all();
    }

    private function increment(string $name, int $by = 1): void
    {
        try {
            Cache::increment(self::PREFIX.$name, $by);
        } catch (\Throwable) {
            // Observability cannot alter an authorization decision.
        }
    }
}
