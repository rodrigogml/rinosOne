<?php

namespace App\Services\Locality;

use App\Jobs\Locality\PostalReferenceEnrichmentJob;

final class PostalReferenceEnrichmentRequestService
{
    public function __construct(
        private readonly LocalityPostalCodeNormalizer $normalizer,
        private readonly PostalReferenceRefreshStateService $refreshStates,
    ) {}

    /**
     * Enqueue one equivalent refresh after the current database transaction commits.
     */
    public function request(string $countryCode, string $postalCode): PostalReferenceRefreshState
    {
        $countryCode = $this->normalizer->normalizeCountryCode($countryCode);
        $normalizedPostalCode = $this->normalizer->normalizePostalCode($postalCode);

        if ($this->refreshStates->start($countryCode, $normalizedPostalCode)) {
            PostalReferenceEnrichmentJob::dispatch($countryCode, $normalizedPostalCode)->afterCommit();
        }

        return $this->refreshStates->state($countryCode, $normalizedPostalCode);
    }
}
