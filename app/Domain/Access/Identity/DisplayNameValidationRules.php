<?php

namespace App\Domain\Access\Identity;

class DisplayNameValidationRules
{
    /** @return list<string> */
    public static function rules(): array
    {
        return ['required', 'string', 'max:255', 'not_regex:/^\s*$/u'];
    }
}
