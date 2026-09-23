<?php

namespace App\Domain\Access\Credential;

final class PasswordStrengthPolicy
{
    private const int MINIMUM_LENGTH = 6;

    private const int MINIMUM_CATEGORIES = 2;

    public function isSatisfiedBy(string $password): bool
    {
        return mb_strlen($password) >= self::MINIMUM_LENGTH
            && $this->categoryCount($password) >= self::MINIMUM_CATEGORIES;
    }

    private function categoryCount(string $password): int
    {
        return count(array_filter([
            preg_match('/\p{Ll}/u', $password) === 1,
            preg_match('/\p{Lu}/u', $password) === 1,
            preg_match('/\p{N}/u', $password) === 1,
            preg_match('/[\p{P}\p{S}]/u', $password) === 1,
        ]));
    }
}
