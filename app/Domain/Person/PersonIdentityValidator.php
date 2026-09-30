<?php

namespace App\Domain\Person;

use App\Domain\Person\Exception\PersonValidationException;
use Carbon\CarbonImmutable;
use Throwable;

class PersonIdentityValidator
{
    /** @return array<string, mixed> */
    public function validate(PersonIdentityInput $input): array
    {
        $errors = [];
        $name = trim($input->name);
        $alias = $this->optional($input->alias);
        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 255) {
            $errors['name'] = 'invalid';
        }
        if ($alias !== null && mb_strlen($alias, 'UTF-8') > 255) {
            $errors['alias'] = 'invalid';
        }

        $cpf = $this->document($input->cpf, PersonDocumentValidator::normalizeCpf(...), 'cpf', $errors);
        $cnpj = $this->document($input->cnpj, PersonDocumentValidator::normalizeCnpj(...), 'cnpj', $errors);
        if ($input->personType === PersonType::PF && $cnpj !== null) {
            $errors['cnpj'] = 'not_applicable';
        }
        if ($input->personType === PersonType::PJ && $cpf !== null) {
            $errors['cpf'] = 'not_applicable';
        }

        foreach (['rg' => $input->rg, 'rgIssuer' => $input->rgIssuer, 'pisNis' => $input->pisNis] as $field => $value) {
            if ($input->personType === PersonType::PJ && $this->optional($value) !== null) {
                $errors[$field] = 'not_applicable';
            }
        }

        $birthDate = $this->date($input->birthDate, 'birthDate', $errors);
        $foundationDate = $this->date($input->foundationDate, 'foundationDate', $errors);
        if ($input->personType === PersonType::PJ && $birthDate !== null) {
            $errors['birthDate'] = 'not_applicable';
        }
        if ($input->personType === PersonType::PF && $foundationDate !== null) {
            $errors['foundationDate'] = 'not_applicable';
        }

        $fields = [
            'rg' => [$input->rg, 40], 'rgIssuer' => [$input->rgIssuer, 60], 'pisNis' => [$input->pisNis, 20],
            'passportNumber' => [$input->passportNumber, 40], 'foreignDocumentNumber' => [$input->foreignDocumentNumber, 60],
        ];
        foreach ($fields as $field => [$value, $maximum]) {
            if (($normalized = $this->optional($value)) !== null && mb_strlen($normalized, 'UTF-8') > $maximum) {
                $errors[$field] = 'invalid';
            }
        }
        $notes = $this->optional($input->notes);
        if ($notes !== null && strlen($notes) > 16_777_215) {
            $errors['notes'] = 'invalid';
        }

        $displayName = $alias === null ? $name : "{$name} ({$alias})";
        if (mb_strlen($displayName, 'UTF-8') > 511) {
            $errors['alias'] = 'display_name_too_long';
        }
        if ($errors !== []) {
            throw new PersonValidationException($errors);
        }

        return [
            'personType' => $input->personType, 'name' => $name, 'alias' => $alias, 'displayName' => $displayName,
            'cpf' => $cpf, 'cnpj' => $cnpj, 'rg' => $this->optional($input->rg), 'rgIssuer' => $this->optional($input->rgIssuer),
            'pisNis' => $this->optional($input->pisNis), 'passportNumber' => $this->optional($input->passportNumber),
            'foreignDocumentNumber' => $this->optional($input->foreignDocumentNumber), 'birthDate' => $birthDate,
            'foundationDate' => $foundationDate, 'notes' => $notes,
        ];
    }

    /** @param array<string, string> $errors */
    private function document(?string $value, callable $normalizer, string $field, array &$errors): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $normalized = $normalizer($value);
        if ($normalized === null) {
            $errors[$field] = 'invalid';
        }

        return $normalized;
    }

    /** @param array<string, string> $errors */
    private function date(?string $value, string $field, array &$errors): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            if ($date === false || $date->format('Y-m-d') !== $value || $date->isFuture()) {
                $errors[$field] = 'invalid';

                return null;
            }

            return $date->toDateString();
        } catch (Throwable) {
            $errors[$field] = 'invalid';

            return null;
        }
    }

    private function optional(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
