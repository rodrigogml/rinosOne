<?php

namespace App\Domain\Person;

use App\Domain\Person\Exception\PersonValidationException;

class PersonPixKeyValidator
{
    /** @return array{keyType: PersonPixKeyType,status: PersonStatus,keyValue:string,normalizedKeyValue:string} */
    public function validate(PersonPixKeyInput $input): array
    {
        $value = trim($input->value);
        $normalized = match ($input->type) {
            PersonPixKeyType::EMAIL => mb_strtolower($value),
            PersonPixKeyType::PHONE, PersonPixKeyType::CPF, PersonPixKeyType::CNPJ => preg_replace('/\D+/', '', $value) ?? '',
            default => $value,
        };
        $valid = $value !== '' && match ($input->type) {
            PersonPixKeyType::EMAIL => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            PersonPixKeyType::PHONE => strlen($normalized) >= 10 && strlen($normalized) <= 15,
            PersonPixKeyType::CPF => strlen($normalized) === 11,
            PersonPixKeyType::CNPJ => strlen($normalized) === 14,
            PersonPixKeyType::RANDOM => preg_match('/^[0-9a-fA-F-]{32,36}$/', $value) === 1,
        };
        if (! $valid) {
            throw new PersonValidationException(['value' => 'invalid_for_type']);
        }

        return ['keyType' => $input->type, 'keyValue' => $value, 'normalizedKeyValue' => $normalized, 'status' => $input->status];
    }
}
