<?php

namespace App\Domain\Person;

final class PersonDocumentValidator
{
    public static function normalizeCpf(?string $value): ?string
    {
        return self::normalize($value, 11, fn (string $digits): bool => self::validCpf($digits));
    }

    public static function normalizeCnpj(?string $value): ?string
    {
        return self::normalize($value, 14, fn (string $digits): bool => self::validCnpj($digits));
    }

    private static function normalize(?string $value, int $length, callable $valid): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/^[0-9.\\/\\-\\s]+$/D', $value) !== 1) {
            return null;
        }

        $digits = preg_replace('/\\D/', '', $value);

        return is_string($digits) && strlen($digits) === $length && $valid($digits) ? $digits : null;
    }

    private static function validCpf(string $cpf): bool
    {
        if (preg_match('/^(\\d)\\1{10}$/D', $cpf) === 1) {
            return false;
        }

        return self::digit($cpf, 9, 10) === (int) $cpf[9]
            && self::digit($cpf, 10, 11) === (int) $cpf[10];
    }

    private static function validCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\\d)\\1{13}$/D', $cnpj) === 1) {
            return false;
        }

        return self::cnpjDigit($cnpj, 12) === (int) $cnpj[12]
            && self::cnpjDigit($cnpj, 13) === (int) $cnpj[13];
    }

    private static function digit(string $value, int $length, int $weight): int
    {
        $sum = 0;
        for ($index = 0; $index < $length; $index++, $weight--) {
            $sum += (int) $value[$index] * $weight;
            if ($weight === 2) {
                $weight = 11;
            }
        }

        $remainder = ($sum * 10) % 11;

        return $remainder === 10 ? 0 : $remainder;
    }

    private static function cnpjDigit(string $value, int $length): int
    {
        $sum = 0;
        $weight = $length === 12 ? 5 : 6;
        for ($index = 0; $index < $length; $index++, $weight--) {
            $sum += (int) $value[$index] * $weight;
            if ($weight === 2) {
                $weight = 10;
            }
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
