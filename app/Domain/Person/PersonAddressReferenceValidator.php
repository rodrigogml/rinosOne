<?php

namespace App\Domain\Person;

use App\Domain\Person\Exception\PersonValidationException;

/** Validates country and Brazilian territory ownership resolved from core catalogs. */
class PersonAddressReferenceValidator
{
    public function assertCompatible(PersonAddressInput $input, bool $countryExists, bool $stateMatchesCountry, bool $municipalityMatchesState, bool $localityMatchesTerritory): void
    {
        $errors = [];
        if (! $countryExists) {
            $errors['idCountry'] = 'not_found';
        }
        if ($input->isBrazil && ! $stateMatchesCountry) {
            $errors['idBrazilState'] = 'incompatible_country';
        }
        if ($input->isBrazil && ! $municipalityMatchesState) {
            $errors['idBrazilMunicipality'] = 'incompatible_state';
        }
        if ($input->localityReferenceId !== null && ! $localityMatchesTerritory) {
            $errors['idLocalityReference'] = 'incompatible_territory';
        }
        if ($errors !== []) {
            throw new PersonValidationException($errors);
        }
    }
}
