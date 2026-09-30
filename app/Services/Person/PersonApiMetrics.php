<?php

namespace App\Services\Person;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Stores bounded, aggregate API measurements for the People capability.
 *
 * Measurements are keyed exclusively by operation, status family and minute.
 * Tenant, user, route parameters, search text and Person attributes are never
 * retained, so this service is safe to use at the HTTP boundary.
 */
final class PersonApiMetrics
{
    /** @var list<int> */
    private const LATENCY_BUCKETS = [50, 100, 250, 500, 1000, 2000, 5000];

    public function __construct(private readonly CacheRepository $cache) {}

    public function record(string $operation, int $status, int $elapsedMilliseconds, ?string $errorCode = null): void
    {
        $operation = $this->operation($operation);
        $minute = now()->utc()->format('YmdHi');
        $expiresAt = now()->addMinutes(max(10, (int) config('person.metricsRetentionMinutes')));
        $prefix = $this->prefix($operation, $minute);

        $this->increment($prefix.':requests', $expiresAt);
        $this->increment($prefix.':status:'.intdiv(max(100, $status), 100).'xx', $expiresAt);
        if ($status === 429) {
            $this->increment($prefix.':rate-limited', $expiresAt);
        }
        if ($errorCode === 'PERSON_VERSION_CONFLICT') {
            $this->increment($prefix.':version-conflicts', $expiresAt);
        }
        if ($status >= 400) {
            $this->increment($prefix.':errors', $expiresAt);
        }
        $this->increment($prefix.':latency:'.$this->bucket($elapsedMilliseconds), $expiresAt);
    }

    /**
     * Returns only a coarse percentile estimate produced from latency buckets.
     *
     * @return array{requestCount:int,errorCount:int,rateLimitedCount:int,versionConflictCount:int,p95Milliseconds:int}
     */
    public function summary(string $operation, int $minutes = 1): array
    {
        $operation = $this->operation($operation);
        $minutes = max(1, min(60, $minutes));
        $requests = 0;
        $errors = 0;
        $rateLimited = 0;
        $conflicts = 0;
        $histogram = array_fill_keys(self::LATENCY_BUCKETS, 0);

        for ($offset = 0; $offset < $minutes; $offset++) {
            $prefix = $this->prefix($operation, now()->utc()->subMinutes($offset)->format('YmdHi'));
            $requests += (int) $this->cache->get($prefix.':requests', 0);
            $errors += (int) $this->cache->get($prefix.':errors', 0);
            $rateLimited += (int) $this->cache->get($prefix.':rate-limited', 0);
            $conflicts += (int) $this->cache->get($prefix.':version-conflicts', 0);
            foreach (self::LATENCY_BUCKETS as $bucket) {
                $histogram[$bucket] += (int) $this->cache->get($prefix.':latency:'.$bucket, 0);
            }
        }

        return [
            'requestCount' => $requests,
            'errorCount' => $errors,
            'rateLimitedCount' => $rateLimited,
            'versionConflictCount' => $conflicts,
            'p95Milliseconds' => $this->p95($histogram, $requests),
        ];
    }

    public function alertIsActive(string $operation): bool
    {
        $summary = $this->summary($operation, (int) config('person.performanceAlertWindowMinutes'));

        return $summary['requestCount'] > 0
            && $summary['p95Milliseconds'] > (int) config('person.performanceAlertP95Milliseconds');
    }

    private function bucket(int $elapsedMilliseconds): int
    {
        foreach (self::LATENCY_BUCKETS as $bucket) {
            if ($elapsedMilliseconds <= $bucket) {
                return $bucket;
            }
        }

        return self::LATENCY_BUCKETS[array_key_last(self::LATENCY_BUCKETS)];
    }

    /** @param array<int, int> $histogram */
    private function p95(array $histogram, int $requestCount): int
    {
        if ($requestCount === 0) {
            return 0;
        }
        $threshold = (int) ceil($requestCount * 0.95);
        $observed = 0;
        foreach (self::LATENCY_BUCKETS as $bucket) {
            $observed += $histogram[$bucket];
            if ($observed >= $threshold) {
                return $bucket;
            }
        }

        return self::LATENCY_BUCKETS[array_key_last(self::LATENCY_BUCKETS)];
    }

    private function prefix(string $operation, string $minute): string
    {
        return 'person:api-metrics:'.$operation.':'.$minute;
    }

    /**
     * Initializes counters with their expiry before incrementing them.
     *
     * Laravel cache increment operations do not attach a TTL by themselves.
     */
    private function increment(string $key, \DateTimeInterface $expiresAt): void
    {
        $this->cache->add($key, 0, $expiresAt);
        $this->cache->increment($key);
    }

    private function operation(string $operation): string
    {
        return preg_replace('/[^a-z0-9._-]/i', '-', $operation) ?: 'unknown';
    }
}
