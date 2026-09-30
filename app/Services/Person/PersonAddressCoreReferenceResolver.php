<?php

namespace App\Services\Person;

use App\Domain\Person\PersonAddressInput;
use App\Domain\Person\PersonAddressReferenceValidator;
use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use App\Models\LocalityReference;

class PersonAddressCoreReferenceResolver
{
    public function __construct(private readonly PersonAddressReferenceValidator $validator) {}

    public function assertCompatible(PersonAddressInput $input): void
    {
        $countryExists = Country::query()->whereKey($input->countryId)->where('activeForSelection', true)->exists();
        $stateMatchesCountry = ! $input->isBrazil || BrazilState::query()->whereKey($input->brazilStateId)->where('idCountry', $input->countryId)->where('activeForSelection', true)->exists();
        $municipalityMatchesState = ! $input->isBrazil || BrazilMunicipality::query()->whereKey($input->brazilMunicipalityId)->where('idBrazilState', $input->brazilStateId)->where('activeForSelection', true)->exists();
        $localityMatchesTerritory = $input->localityReferenceId === null || LocalityReference::query()->whereKey($input->localityReferenceId)->where('idCountry', $input->countryId)->where('status', 'ACTIVE')->when($input->isBrazil, fn ($query) => $query->where('idBrazilState', $input->brazilStateId)->where('idBrazilMunicipality', $input->brazilMunicipalityId))->exists();
        $this->validator->assertCompatible($input, $countryExists, $stateMatchesCountry, $municipalityMatchesState, $localityMatchesTerritory);
    }
}
