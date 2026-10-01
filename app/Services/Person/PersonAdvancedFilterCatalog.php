<?php

namespace App\Services\Person;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Defines and executes the explicitly supported advanced filters for People.
 *
 * The public schema is deliberately independent of physical column names so a
 * client can build a reusable filter editor without receiving database details.
 */
final class PersonAdvancedFilterCatalog
{
    public const MAX_DEPTH = 5;

    public const MAX_CONDITIONS = 50;

    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'maximumDepth' => self::MAX_DEPTH,
            'maximumConditions' => self::MAX_CONDITIONS,
            'relations' => [
                ['key' => 'bankAccounts', 'label' => 'Dados bancários'],
            ],
            'fields' => [
                $this->field('displayName', 'Nome de exibição', 'TEXT'),
                $this->field('name', 'Nome / razão social', 'TEXT'),
                $this->field('alias', 'Apelido / nome fantasia', 'TEXT'),
                $this->field('cpf', 'CPF', 'TEXT'),
                $this->field('cnpj', 'CNPJ', 'TEXT'),
                $this->field('personType', 'Tipo de pessoa', 'ENUM', null, [['value' => 'PF', 'label' => 'Pessoa física'], ['value' => 'PJ', 'label' => 'Pessoa jurídica']]),
                $this->field('status', 'Situação', 'ENUM', null, [['value' => 'ACTIVE', 'label' => 'Ativa'], ['value' => 'INACTIVE', 'label' => 'Inativa']]),
                $this->field('bankAccounts.financialInstitution', 'Dados bancários › Banco', 'TEXT', 'bankAccounts'),
                $this->field('bankAccounts.label', 'Dados bancários › Identificação', 'TEXT', 'bankAccounts'),
                $this->field('bankAccounts.accountType', 'Dados bancários › Tipo de conta', 'ENUM', 'bankAccounts', [['value' => 'CHECKING', 'label' => 'Corrente'], ['value' => 'SAVINGS', 'label' => 'Poupança'], ['value' => 'INVESTMENT', 'label' => 'Investimento'], ['value' => 'SALARY', 'label' => 'Salário'], ['value' => 'OTHER', 'label' => 'Outro']]),
                $this->field('bankAccounts.status', 'Dados bancários › Situação', 'ENUM', 'bankAccounts', [['value' => 'ACTIVE', 'label' => 'Ativa'], ['value' => 'INACTIVE', 'label' => 'Inativa']]),
                $this->field('bankAccounts.agency', 'Dados bancários › Agência', 'TEXT', 'bankAccounts'),
                $this->field('bankAccounts.accountNumber', 'Dados bancários › Conta', 'TEXT', 'bankAccounts'),
            ],
        ];
    }

    /** @return array<string, list<string>> */
    public function errors(mixed $filter): array
    {
        if ($filter === null) return [];
        if (! is_array($filter)) return ['advancedFilter' => ['O filtro avançado precisa ser uma árvore de condições.']];

        $errors = [];
        $conditions = 0;
        $this->validateNode($filter, 1, null, $conditions, $errors, 'advancedFilter');

        if ($conditions > self::MAX_CONDITIONS) $errors['advancedFilter'][] = 'O filtro avançado aceita no máximo '.self::MAX_CONDITIONS.' condições.';

        return $errors;
    }

    public function apply(Builder $query, array $filter): void
    {
        $this->applyGroup($query, $filter, 'and', null);
    }

    /** @param array<string, mixed> $node */
    public function usesField(array $node, string $field): bool
    {
        if (($node['kind'] ?? null) === 'condition') return ($node['field'] ?? null) === $field;
        foreach ($node['children'] ?? [] as $child) if (is_array($child) && $this->usesField($child, $field)) return true;

        return false;
    }

    /** @return array<string, mixed> */
    private function field(string $key, string $label, string $type, ?string $relation = null, array $options = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'relation' => $relation, 'operators' => $type === 'TEXT' ? ['EQUALS', 'NOT_EQUALS', 'CONTAINS', 'NOT_CONTAINS', 'STARTS_WITH', 'ENDS_WITH', 'IS_EMPTY', 'IS_NOT_EMPTY'] : ['EQUALS', 'NOT_EQUALS', 'IN', 'NOT_IN'], 'options' => $options];
    }

    /** @param array<string, mixed> $node @param array<string, list<string>> $errors */
    private function validateNode(array $node, int $depth, ?string $scope, int &$conditions, array &$errors, string $path): void
    {
        if ($depth > self::MAX_DEPTH) { $errors[$path][] = 'O filtro avançado aceita até '.self::MAX_DEPTH.' níveis de grupos.'; return; }
        $kind = $node['kind'] ?? null;
        if ($kind === 'condition') {
            $conditions++;
            $field = is_string($node['field'] ?? null) ? $node['field'] : '';
            $definition = $this->fieldDefinition($field);
            if ($definition === null) { $errors[$path.'.field'][] = 'O campo informado não está disponível para este filtro.'; return; }
            if ($scope !== null && $definition['relation'] !== $scope) $errors[$path.'.field'][] = 'O campo informado não pertence ao contexto do grupo.';
            $operator = is_string($node['operator'] ?? null) ? $node['operator'] : '';
            if (! in_array($operator, $definition['operators'], true)) $errors[$path.'.operator'][] = 'O operador informado não é compatível com este campo.';
            if (! in_array($operator, ['IS_EMPTY', 'IS_NOT_EMPTY'], true) && ! array_key_exists('value', $node)) $errors[$path.'.value'][] = 'Informe um valor para esta condição.';
            return;
        }
        if ($kind !== 'group') { $errors[$path.'.kind'][] = 'O filtro contém um nó inválido.'; return; }
        if (! in_array($node['combinator'] ?? null, ['AND', 'OR'], true)) $errors[$path.'.combinator'][] = 'Selecione E ou OU para o grupo.';
        $mode = $node['matchMode'] ?? 'ANY_RECORD';
        $relation = $node['relation'] ?? null;
        if (! in_array($mode, ['ANY_RECORD', 'SAME_RECORD'], true)) $errors[$path.'.matchMode'][] = 'O modo de correspondência informado é inválido.';
        if ($mode === 'SAME_RECORD' && ! in_array($relation, ['bankAccounts'], true)) $errors[$path.'.relation'][] = 'Escolha uma coleção para o grupo de mesmo registro.';
        if ($scope !== null && ($mode !== 'SAME_RECORD' || $relation !== $scope)) $errors[$path][] = 'Um grupo contextualizado não pode mudar a coleção do grupo pai.';
        $children = $node['children'] ?? null;
        if (! is_array($children) || $children === []) { $errors[$path.'.children'][] = 'Inclua ao menos uma condição no grupo.'; return; }
        foreach ($children as $index => $child) {
            if (! is_array($child)) { $errors[$path.'.children.'.$index][] = 'O filtro contém um nó inválido.'; continue; }
            $this->validateNode($child, $depth + 1, $mode === 'SAME_RECORD' ? $relation : $scope, $conditions, $errors, $path.'.children.'.$index);
        }
    }

    private function applyGroup(Builder $query, array $group, string $boolean, ?string $scope): void
    {
        $mode = $group['matchMode'] ?? 'ANY_RECORD';
        $relation = $group['relation'] ?? null;
        if ($mode === 'SAME_RECORD' && $relation === 'bankAccounts' && $scope === null) {
            $method = ($group['negated'] ?? false) ? 'whereDoesntHave' : ($boolean === 'or' ? 'orWhereHas' : 'whereHas');
            $query->{$method}('bankAccounts', function (Builder $accounts) use ($group): void { $this->applyScopedChildren($accounts, $group['children'], 'and'); });
            return;
        }

        $method = ($group['negated'] ?? false) ? 'whereNot' : ($boolean === 'or' ? 'orWhere' : 'where');
        $query->{$method}(function (Builder $nested) use ($group, $scope): void {
            foreach ($group['children'] as $index => $child) {
                $childBoolean = $index === 0 ? 'and' : strtolower($group['combinator']);
                if ($child['kind'] === 'group') $this->applyGroup($nested, $child, $childBoolean, $scope);
                else $this->applyCondition($nested, $child, $childBoolean, $scope);
            }
        });
    }

    /** @param list<array<string, mixed>> $children */
    private function applyScopedChildren(Builder $query, array $children, string $boolean): void
    {
        foreach ($children as $index => $child) {
            $childBoolean = $index === 0 ? 'and' : $boolean;
            if ($child['kind'] === 'group') {
                $method = ($child['negated'] ?? false) ? 'whereNot' : ($childBoolean === 'or' ? 'orWhere' : 'where');
                $query->{$method}(function (Builder $nested) use ($child): void { $this->applyScopedChildren($nested, $child['children'], strtolower($child['combinator'])); });
            } else $this->applyCondition($query, $child, $childBoolean, 'bankAccounts');
        }
    }

    /** @param array<string, mixed> $condition */
    private function applyCondition(Builder $query, array $condition, string $boolean, ?string $scope): void
    {
        $definition = $this->fieldDefinition($condition['field']);
        if ($definition['relation'] === 'bankAccounts' && $scope === null) {
            $method = ($condition['negated'] ?? false) ? 'whereDoesntHave' : ($boolean === 'or' ? 'orWhereHas' : 'whereHas');
            $positiveCondition = [...$condition, 'negated' => false];
            $query->{$method}('bankAccounts', function (Builder $accounts) use ($positiveCondition): void { $this->applyCondition($accounts, $positiveCondition, 'and', 'bankAccounts'); });
            return;
        }
        $method = ($condition['negated'] ?? false) ? 'whereNot' : ($boolean === 'or' ? 'orWhere' : 'where');
        $query->{$method}(function (Builder $nested) use ($condition, $definition): void { $this->applyPredicate($nested, $definition['key'], $condition['operator'], $condition['value'] ?? null); });
    }

    private function applyPredicate(Builder $query, string $field, string $operator, mixed $value): void
    {
        $column = match ($field) {
            'displayName', 'name', 'alias', 'cpf', 'cnpj', 'personType', 'status' => $field,
            'bankAccounts.label' => 'label', 'bankAccounts.accountType' => 'accountType', 'bankAccounts.status' => 'status', 'bankAccounts.agency' => 'agency', 'bankAccounts.accountNumber' => 'accountNumber',
            default => null,
        };
        if ($field === 'bankAccounts.financialInstitution') {
            $schema = (string) config('database.connections.core.database');
            $needle = $this->likeValue((string) $value, 'CONTAINS');
            $query->whereIn('idFinancialInstitution', function (QueryBuilder $institutions) use ($schema, $needle): void {
                $institutions->from($schema.'.financialInstitution')->select('id')->where('legalName', 'like', $needle)->orWhere('reducedName', 'like', $needle)->orWhere('tradeName', 'like', $needle)->orWhere('compeCode', 'like', $needle);
            });
            return;
        }
        if (in_array($operator, ['IS_EMPTY', 'IS_NOT_EMPTY'], true)) {
            $operator === 'IS_EMPTY' ? $query->where(function (Builder $empty) use ($column): void { $empty->whereNull($column)->orWhere($column, ''); }) : $query->whereNotNull($column)->where($column, '<>', '');
            return;
        }
        if (in_array($operator, ['IN', 'NOT_IN'], true)) { $query->whereIn($column, is_array($value) ? $value : [$value], 'and', $operator === 'NOT_IN'); return; }
        $sqlOperator = match ($operator) { 'NOT_EQUALS' => '<>', 'NOT_CONTAINS' => 'not like', default => $operator === 'EQUALS' ? '=' : 'like' };
        $query->where($column, $sqlOperator, in_array($operator, ['CONTAINS', 'NOT_CONTAINS', 'STARTS_WITH', 'ENDS_WITH'], true) ? $this->likeValue((string) $value, $operator) : $value);
    }

    private function likeValue(string $value, string $operator): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        return match ($operator) { 'STARTS_WITH' => $escaped.'%', 'ENDS_WITH' => '%'.$escaped, default => '%'.$escaped.'%' };
    }

    /** @return array<string, mixed>|null */
    private function fieldDefinition(string $key): ?array
    {
        foreach ($this->schema()['fields'] as $field) if ($field['key'] === $key) return $field;
        return null;
    }
}
