<?php

namespace App\Infrastructure\Locality;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Throwable;

final class PostalReferenceSourceBatchService
{
    /**
     * This composition is intentionally explicit: there is no provider discovery mechanism.
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ViaCepPostalReferenceSource $viaCep,
        private readonly BrasilApiPostalReferenceSource $brasilApi,
    ) {}

    public function fetch(string $countryCode, string $normalizedPostalCode): PostalReferenceSourceBatchResult
    {
        $sources = array_values(array_filter(
            [$this->viaCep, $this->brasilApi],
            static fn (PostalReferenceHttpSource $source): bool => $source->enabled() && $source->supports($countryCode, $normalizedPostalCode),
        ));

        if ($sources === []) {
            return new PostalReferenceSourceBatchResult([]);
        }

        $responses = $this->http->acceptJson()->pool(function (Pool $pool) use ($sources, $countryCode, $normalizedPostalCode): void {
            foreach ($sources as $source) {
                $source->enqueue($pool, $countryCode, $normalizedPostalCode);
            }
        });

        return new PostalReferenceSourceBatchResult(array_map(
            fn (PostalReferenceHttpSource $source): PostalReferenceSourceFetchResult => $this->resultFor($source, $responses[$source->sourceKey()] ?? null, $countryCode, $normalizedPostalCode),
            $sources,
        ));
    }

    private function resultFor(
        PostalReferenceHttpSource $source,
        mixed $response,
        string $countryCode,
        string $normalizedPostalCode,
    ): PostalReferenceSourceFetchResult {
        try {
            if (! $response instanceof Response) {
                return new PostalReferenceSourceFetchResult($source->sourceKey(), false, []);
            }

            if ($source->isNotFound($response)) {
                return new PostalReferenceSourceFetchResult($source->sourceKey(), true, []);
            }

            $response->throw();

            return new PostalReferenceSourceFetchResult(
                $source->sourceKey(),
                true,
                $source->recordsFromResponse($response, $countryCode, $normalizedPostalCode),
            );
        } catch (Throwable) {
            return new PostalReferenceSourceFetchResult($source->sourceKey(), false, []);
        }
    }
}
