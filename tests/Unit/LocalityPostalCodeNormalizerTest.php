<?php

namespace Tests\Unit;

use App\Services\Locality\LocalityPostalCodeNormalizer;
use InvalidArgumentException;
use Tests\TestCase;

class LocalityPostalCodeNormalizerTest extends TestCase
{
    public function test_normalizes_country_and_postal_codes_without_using_the_postal_code_as_a_street_identity(): void
    {
        $normalizer = new LocalityPostalCodeNormalizer;

        $this->assertSame('BR', $normalizer->normalizeCountryCode(' br '));
        $this->assertSame('01001000', $normalizer->normalizePostalCode('01001-000'));
        $this->assertSame('SW1A1AA', $normalizer->normalizePostalCode('sw1a 1aa'));
    }

    public function test_rejects_country_codes_that_are_not_iso_alpha_two(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LocalityPostalCodeNormalizer)->normalizeCountryCode('BRA');
    }

    public function test_rejects_empty_and_too_long_postal_codes(): void
    {
        $normalizer = new LocalityPostalCodeNormalizer;

        try {
            $normalizer->normalizePostalCode('---');
            $this->fail('An empty postal code should be rejected.');
        } catch (InvalidArgumentException) {
            $this->expectException(InvalidArgumentException::class);
        }

        $normalizer->normalizePostalCode(str_repeat('A', 33));
    }
}
