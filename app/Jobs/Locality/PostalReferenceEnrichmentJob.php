<?php

namespace App\Jobs\Locality;

use App\Infrastructure\Locality\PostalReferenceSourceBatchService;
use App\Services\Locality\PostalReferenceRefreshStateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PostalReferenceEnrichmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $countryCode,
        public readonly string $normalizedPostalCode,
    ) {}

    /**
     * Continues the postal-source refresh outside the originating HTTP request.
     */
    public function handle(
        PostalReferenceSourceBatchService $sources,
        PostalReferenceRefreshStateService $refreshStates,
    ): void {
        $lock = Cache::lock(
            $refreshStates->lockKey($this->countryCode, $this->normalizedPostalCode),
            max(1, (int) config('localities.postal.enrichment_lock_seconds')),
        );

        if (! $lock->get()) {
            return;
        }

        try {
            $result = $sources->fetch($this->countryCode, $this->normalizedPostalCode);

            $refreshStates->complete($this->countryCode, $this->normalizedPostalCode, $result->hasFailures());
        } catch (Throwable) {
            $refreshStates->complete($this->countryCode, $this->normalizedPostalCode, true);
        } finally {
            $lock->release();
        }
    }
}
