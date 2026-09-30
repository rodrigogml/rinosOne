<?php

namespace App\Domain\Person\Deletion;

use InvalidArgumentException;

/**
 * Safe, actionable information about a known module usage that prevents a
 * Person from being physically deleted.
 *
 * The description is intentionally generic: it must never include record
 * identifiers, values supplied by users, or implementation details.
 */
readonly class PersonUsage
{
    public function __construct(
        public string $module,
        public string $description,
    ) {
        if (trim($module) === '' || mb_strlen($module, 'UTF-8') > 120) {
            throw new InvalidArgumentException('The usage module must be a concise, non-empty label.');
        }

        if (trim($description) === '' || mb_strlen($description, 'UTF-8') > 255) {
            throw new InvalidArgumentException('The usage description must be a concise, non-empty message.');
        }
    }
}
