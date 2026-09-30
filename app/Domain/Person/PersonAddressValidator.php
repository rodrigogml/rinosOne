<?php

namespace App\Domain\Person;

use App\Domain\Person\Exception\PersonValidationException;

class PersonAddressValidator
{
    /** @return array<string, int|string|null> */
    public function validate(PersonAddressInput $input): array
    {
        $errors = [];
        if (trim($input->label) === '' || mb_strlen(trim($input->label), 'UTF-8') > 60) {
            $errors['label'] = 'required';
        }
        if ($input->countryId < 1) {
            $errors['idCountry'] = 'required';
        }
        if ($input->isBrazil && $input->brazilStateId === null) {
            $errors['idBrazilState'] = 'required_for_brazil';
        }
        if ($input->isBrazil && $input->brazilMunicipalityId === null) {
            $errors['idBrazilMunicipality'] = 'required_for_brazil';
        }
        if ($input->street !== null && mb_strlen(trim($input->street), 'UTF-8') > 255) {
            $errors['street'] = 'invalid';
        }
        foreach ([
            'number' => [$input->number, 40],
            'postalCode' => [$input->postalCode, 24],
            'stateText' => [$input->stateText, 120],
            'cityText' => [$input->cityText, 120],
            'complement' => [$input->complement, 255],
            'district' => [$input->district, 255],
            'reference' => [$input->reference, 255],
        ] as $field => [$value, $maximumLength]) {
            if ($value !== null && mb_strlen(trim($value), 'UTF-8') > $maximumLength) {
                $errors[$field] = 'invalid';
            }
        }
        if ($errors !== []) {
            throw new PersonValidationException($errors);
        }

        return [
            'label' => trim($input->label),
            'addressType' => $input->addressType,
            'idCountry' => $input->countryId,
            'idBrazilState' => $input->brazilStateId,
            'idBrazilMunicipality' => $input->brazilMunicipalityId,
            'idLocalityReference' => $input->localityReferenceId,
            'stateText' => $input->isBrazil ? null : $this->optional($input->stateText),
            'cityText' => $input->isBrazil ? null : $this->optional($input->cityText),
            'street' => $this->optional($input->street),
            'number' => $this->optional($input->number),
            'complement' => $this->optional($input->complement),
            'district' => $this->optional($input->district),
            'reference' => $this->optional($input->reference),
            'postalCode' => $this->optional($input->postalCode),
        ];
    }

    private function optional(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
