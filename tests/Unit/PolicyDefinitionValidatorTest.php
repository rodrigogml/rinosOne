<?php

namespace Tests\Unit;

use App\Services\Authorization\Advanced\PolicyDefinitionValidator;
use LogicException;
use Tests\TestCase;

class PolicyDefinitionValidatorTest extends TestCase
{
    public function test_it_normalizes_supported_closed_conditions(): void
    {
        $definition = app(PolicyDefinitionValidator::class)->validate(['all' => [
            ['type' => 'AMOUNT_MAXIMUM', 'maximum' => 10000],
            ['type' => 'ORGANIZATIONAL_UNIT_IN', 'values' => [2, 2, 7]],
        ]]);

        $this->assertSame(['all' => [
            ['type' => 'AMOUNT_MAXIMUM', 'maximum' => 10000],
            ['type' => 'ORGANIZATIONAL_UNIT_IN', 'values' => [2, 7]],
        ]], $definition);
    }

    public function test_it_rejects_unknown_client_expressions(): void
    {
        $validator = app(PolicyDefinitionValidator::class);
        $this->expectException(LogicException::class);
        $validator->validate(['all' => [['type' => 'PHP_EXPRESSION', 'expression' => 'allow()']]]);
    }

    public function test_it_rejects_a_tree_deeper_than_the_supported_limit(): void
    {
        $this->expectException(LogicException::class);
        app(PolicyDefinitionValidator::class)->validate(['all' => [['any' => [['all' => [['any' => [['type' => 'AMOUNT_MAXIMUM', 'maximum' => 1]]]]]]]]]);
    }

    public function test_it_rejects_untrusted_subject_attributes(): void
    {
        $this->expectException(LogicException::class);
        app(PolicyDefinitionValidator::class)->validate(['any' => [['type' => 'SUBJECT_ATTRIBUTE_EQUALS', 'attribute' => 'isAdministrator', 'value' => 'true']]]);
    }

    public function test_it_rejects_more_than_twenty_conditions_across_nested_branches(): void
    {
        $conditions = [];
        for ($index = 1; $index <= 21; $index++) {
            $conditions[] = ['type' => 'AMOUNT_MAXIMUM', 'maximum' => $index];
        }

        $this->expectException(LogicException::class);
        app(PolicyDefinitionValidator::class)->validate(['all' => [
            ['any' => array_slice($conditions, 0, 7)],
            ['any' => array_slice($conditions, 7, 7)],
            ['any' => array_slice($conditions, 14, 7)],
        ]]);
    }
}
