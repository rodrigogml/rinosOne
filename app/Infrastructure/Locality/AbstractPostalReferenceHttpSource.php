<?php

namespace App\Infrastructure\Locality;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use UnexpectedValueException;

abstract class AbstractPostalReferenceHttpSource implements PostalReferenceHttpSource
{
    public function __construct(private readonly HttpFactory $http) {}

    public function enabled(): bool
    {
        return (bool) config($this->configurationKey().'.enabled');
    }

    public function supports(string $countryCode, string $normalizedPostalCode): bool
    {
        return strtoupper($countryCode) === 'BR' && preg_match('/^\d{8}$/', $normalizedPostalCode) === 1;
    }

    public function fetch(string $countryCode, string $normalizedPostalCode): array
    {
        if (! $this->enabled() || ! $this->supports($countryCode, $normalizedPostalCode)) {
            return [];
        }

        $response = $this->request($this->http->acceptJson())
            ->get($this->endpoint($normalizedPostalCode));

        if ($this->isNotFound($response)) {
            return [];
        }

        $response->throw();

        return $this->recordsFromResponse($response, $countryCode, $normalizedPostalCode);
    }

    public function enqueue(Pool $pool, string $countryCode, string $normalizedPostalCode): void
    {
        $this->request($pool->as($this->sourceKey()))
            ->get($this->endpoint($normalizedPostalCode));
    }

    public function isNotFound(Response $response): bool
    {
        return $response->status() === 404;
    }

    protected function request(PendingRequest $request): PendingRequest
    {
        $request = $request
            ->acceptJson()
            ->timeout((int) config($this->configurationKey().'.timeout_seconds'));
        $caBundle = config('localities.postal.ca_bundle');

        if (is_string($caBundle) && $caBundle !== '') {
            return $request->withOptions(['verify' => $caBundle]);
        }

        $localBundle = storage_path('app/certificates/cacert.pem');

        return is_file($localBundle) ? $request->withOptions(['verify' => $localBundle]) : $request;
    }

    /** @param array<string, mixed> $payload */
    protected function record(
        array $payload,
        string $countryCode,
        string $normalizedPostalCode,
        ?string $streetName,
        ?string $neighborhoodName,
        ?string $municipalityName,
        ?string $stateAbbreviation,
        ?string $municipalityIbgeCode,
    ): PostalReferenceSourceRecord {
        $observedValues = [
            'streetName' => $streetName,
            'neighborhoodName' => $neighborhoodName,
            'municipalityName' => $municipalityName,
            'stateAbbreviation' => $stateAbbreviation,
            'municipalityIbgeCode' => $municipalityIbgeCode,
        ];
        $identitySignature = hash('sha256', json_encode([
            'sourceKey' => $this->sourceKey(),
            'countryCode' => strtoupper($countryCode),
            'normalizedPostalCode' => $normalizedPostalCode,
            'observedValues' => $observedValues,
        ], JSON_THROW_ON_ERROR));

        return new PostalReferenceSourceRecord(
            sourceKey: $this->sourceKey(),
            externalIdentifier: $this->optionalString($payload, 'cep'),
            identitySignature: $identitySignature,
            countryCode: strtoupper($countryCode),
            normalizedPostalCode: $normalizedPostalCode,
            streetName: $streetName,
            neighborhoodName: $neighborhoodName,
            municipalityName: $municipalityName,
            stateAbbreviation: $stateAbbreviation,
            municipalityIbgeCode: $municipalityIbgeCode,
            observedValues: $observedValues,
        );
    }

    /** @param array<string, mixed> $payload */
    protected function requiredString(array $payload, string $key): string
    {
        $value = $this->optionalString($payload, $key);

        if ($value === null) {
            throw new UnexpectedValueException("{$this->sourceKey()} response is missing required field {$key}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    protected function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    abstract protected function configurationKey(): string;

    abstract protected function endpoint(string $normalizedPostalCode): string;
}
