<?php

namespace App\Domain\Person;

use App\Domain\Person\Exception\PersonValidationException;

class PersonContactValidator
{
    /** @return array{contactType: PersonContactType, value: string, normalizedValue: string, description: ?string} */
    public function validate(PersonContactInput $input): array
    {
        $value = trim($input->value);
        $normalized = match ($input->type) {
            PersonContactType::EMAIL => mb_strtolower($value),
            PersonContactType::PHONE, PersonContactType::MOBILE, PersonContactType::WHATSAPP => preg_replace('/\D+/', '', $value) ?? '',
            default => $value,
        };
        $valid = $value !== '' && match ($input->type) {
            PersonContactType::EMAIL => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            PersonContactType::PHONE, PersonContactType::MOBILE, PersonContactType::WHATSAPP => strlen($normalized) >= 8 && strlen($normalized) <= 15,
            PersonContactType::WEBSITE => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => true,
        };
        if (! $valid) {
            throw new PersonValidationException(['value' => 'invalid_for_type']);
        }

        return ['contactType' => $input->type, 'value' => $value, 'normalizedValue' => $normalized, 'description' => ($description = trim((string) $input->description)) === '' ? null : $description];
    }
}
