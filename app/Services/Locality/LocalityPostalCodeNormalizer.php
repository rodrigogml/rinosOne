<?php

namespace App\Services\Locality;

use InvalidArgumentException;

final class LocalityPostalCodeNormalizer
{
    public function normalizeCountryCode(string $countryCode): string
    {
        $normalized = strtoupper(trim($countryCode));

        if (preg_match('/^[A-Z]{2}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('The country code must be a valid ISO alpha-2 value.');
        }

        return $normalized;
    }

    public function normalizePostalCode(string $postalCode): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]/u', '', trim($postalCode)) ?? '';
        $normalized = mb_strtoupper($normalized, 'UTF-8');

        if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 32) {
            throw new InvalidArgumentException('The postal code is invalid.');
        }

        return $normalized;
    }
}
