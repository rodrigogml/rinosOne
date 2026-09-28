<?php

namespace App\Services\Authorization\Advanced;

use LogicException;

/** Defines the only condition shapes an advanced authorization policy may use. */
class PolicyConditionCatalog
{
    /** @return list<string> */
    public function types(): array
    {
        return ['AMOUNT_MAXIMUM', 'ORGANIZATIONAL_UNIT_IN', 'TIME_WINDOW_UTC', 'LOGICAL_LOCATION_IN', 'SUBJECT_ATTRIBUTE_EQUALS'];
    }

    /** @param array<string, mixed> $condition @return array<string, mixed> */
    public function normalize(array $condition): array
    {
        $type = $condition['type'] ?? null;
        if (! is_string($type) || ! in_array($type, $this->types(), true)) {
            throw new LogicException('The authorization policy condition type is not supported.');
        }

        return match ($type) {
            'AMOUNT_MAXIMUM' => $this->amountMaximum($condition),
            'ORGANIZATIONAL_UNIT_IN' => $this->identifierList($condition, 'organizationUnitId'),
            'TIME_WINDOW_UTC' => $this->timeWindow($condition),
            'LOGICAL_LOCATION_IN' => $this->stringList($condition, 'logicalLocation'),
            'SUBJECT_ATTRIBUTE_EQUALS' => $this->subjectAttribute($condition),
        };
    }

    /** @param array<string, mixed> $condition @return array<string, mixed> */
    private function amountMaximum(array $condition): array
    {
        $maximum = $condition['maximum'] ?? null;
        if (! is_int($maximum) && ! is_float($maximum)) {
            throw new LogicException('An amount policy requires a numeric maximum.');
        }
        if ($maximum < 0 || $maximum > 999999999999.99) {
            throw new LogicException('An amount policy maximum is outside the supported range.');
        }

        return ['type' => 'AMOUNT_MAXIMUM', 'maximum' => $maximum];
    }

    /** @param array<string, mixed> $condition @return array<string, mixed> */
    private function identifierList(array $condition, string $attribute): array
    {
        $values = $condition['values'] ?? null;
        if (! is_array($values) || count($values) < 1 || count($values) > 50 || array_filter($values, static fn ($value): bool => ! is_int($value) || $value < 1) !== []) {
            throw new LogicException("The {$attribute} policy values are invalid.");
        }

        return ['type' => 'ORGANIZATIONAL_UNIT_IN', 'values' => array_values(array_unique($values))];
    }

    /** @param array<string, mixed> $condition @return array<string, mixed> */
    private function timeWindow(array $condition): array
    {
        $startMinute = $condition['startMinute'] ?? null;
        $endMinute = $condition['endMinute'] ?? null;
        if (! is_int($startMinute) || ! is_int($endMinute) || $startMinute < 0 || $startMinute > 1439 || $endMinute < 0 || $endMinute > 1439 || $startMinute === $endMinute) {
            throw new LogicException('A time window policy requires two distinct UTC minutes.');
        }

        return ['type' => 'TIME_WINDOW_UTC', 'startMinute' => $startMinute, 'endMinute' => $endMinute];
    }

    /** @param array<string, mixed> $condition @return array<string, mixed> */
    private function stringList(array $condition, string $attribute): array
    {
        $values = $condition['values'] ?? null;
        if (! is_array($values) || count($values) < 1 || count($values) > 50 || array_filter($values, static fn ($value): bool => ! is_string($value) || trim($value) === '' || mb_strlen($value) > 160) !== []) {
            throw new LogicException("The {$attribute} policy values are invalid.");
        }

        return ['type' => 'LOGICAL_LOCATION_IN', 'values' => array_values(array_unique(array_map('trim', $values)))];
    }

    /** @param array<string, mixed> $condition @return array<string, mixed> */
    private function subjectAttribute(array $condition): array
    {
        $attribute = $condition['attribute'] ?? null;
        $value = $condition['value'] ?? null;
        if (! in_array($attribute, ['departmentCode', 'employmentType'], true) || ! is_string($value) || trim($value) === '' || mb_strlen($value) > 160) {
            throw new LogicException('The subject attribute policy is invalid.');
        }

        return ['type' => 'SUBJECT_ATTRIBUTE_EQUALS', 'attribute' => $attribute, 'value' => trim($value)];
    }
}
