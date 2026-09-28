<?php

namespace App\Services\Locality;

use App\Models\Country;
use App\Models\LocalityReference;
use App\Models\PostalCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class LocalityReferenceQueryService
{
    public function __construct(private readonly LocalityPostalCodeNormalizer $normalizer) {}

    /**
     * @return Collection<int, LocalityReference>
     */
    public function findActiveByPostalCode(string $countryCode, string $postalCode): Collection
    {
        $normalizedCountryCode = $this->normalizer->normalizeCountryCode($countryCode);
        $normalizedPostalCode = $this->normalizer->normalizePostalCode($postalCode);
        $country = Country::query()->where('isoAlpha2', $normalizedCountryCode)->first();

        if ($country === null) {
            return new Collection;
        }

        $postalCodeRecord = PostalCode::query()
            ->where('idCountry', $country->id)
            ->where('normalizedValue', $normalizedPostalCode)
            ->first();

        if ($postalCodeRecord === null) {
            return new Collection;
        }

        return LocalityReference::query()
            ->active()
            ->where('idCountry', $country->id)
            ->whereHas('postalCodes', fn (Builder $query): Builder => $query->whereKey($postalCodeRecord->id))
            ->with([
                'country',
                'brazilState',
                'brazilMunicipality.brazilState',
                'postalCodes' => fn (BelongsToMany $query): BelongsToMany => $query->whereKey($postalCodeRecord->id),
            ])
            ->orderBy('localityKind')
            ->orderBy('streetName')
            ->orderBy('neighborhoodName')
            ->orderBy('id')
            ->get();
    }
}
