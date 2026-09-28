<?php

namespace App\Services\Authorization\Advanced;

use LogicException;

/** Validates bounded, declarative policy trees without evaluating client-provided expressions. */
class PolicyDefinitionValidator
{
    public function __construct(private readonly PolicyConditionCatalog $catalog) {}

    /** @param array<string, mixed> $definition @return array<string, mixed> */
    public function validate(array $definition): array
    {
        $conditionCount = 0;

        return $this->validateNode($definition, 1, $conditionCount);
    }

    /** @param array<string, mixed> $node @return array<string, mixed> */
    private function validateNode(array $node, int $depth, int &$conditionCount): array
    {
        if ($depth > 3 || count($node) !== 1) {
            throw new LogicException('The authorization policy definition exceeds its supported complexity.');
        }
        $operator = array_key_first($node);
        $children = $node[$operator];
        if (! in_array($operator, ['all', 'any'], true) || ! is_array($children) || count($children) < 1 || count($children) > 10) {
            throw new LogicException('The authorization policy definition is invalid.');
        }

        $normalized = [];
        foreach ($children as $child) {
            if (! is_array($child) || ++$conditionCount > 20) {
                throw new LogicException('The authorization policy definition exceeds its supported complexity.');
            }
            $normalized[] = array_key_exists('type', $child)
                ? $this->catalog->normalize($child)
                : $this->validateNode($child, $depth + 1, $conditionCount);
        }

        return [$operator => $normalized];
    }
}
