<?php

namespace Tests\Unit;

use App\Domain\Access\Credential\PasswordStrengthPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PasswordStrengthPolicyTest extends TestCase
{
    #[DataProvider('invalidPasswords')]
    public function test_rejects_passwords_that_do_not_meet_length_or_category_requirements(string $password): void
    {
        $this->assertFalse((new PasswordStrengthPolicy)->isSatisfiedBy($password));
    }

    #[DataProvider('validPasswords')]
    public function test_accepts_passwords_with_at_least_six_characters_and_two_categories(string $password): void
    {
        $this->assertTrue((new PasswordStrengthPolicy)->isSatisfiedBy($password));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPasswords(): array
    {
        return [
            'fewer than six characters' => ['Ab1!x'],
            'only lowercase letters' => ['abcdef'],
            'only uppercase letters' => ['ABCDEF'],
            'only digits' => ['123456'],
            'only symbols' => ['!@#$%^'],
            'whitespace does not constitute a category' => ['abcde '],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validPasswords(): array
    {
        return [
            'lowercase and digit' => ['abcde1'],
            'uppercase and symbol' => ['ABCDE!'],
            'lowercase uppercase digit and symbol' => ['Abc1!d'],
            'unicode letter and symbol' => ['ábcde!'],
        ];
    }
}
