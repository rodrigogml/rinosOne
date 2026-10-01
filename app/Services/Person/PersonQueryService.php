<?php

namespace App\Services\Person;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Models\Person;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

class PersonQueryService
{
    public function __construct(private readonly PersonAdvancedFilterCatalog $advancedFilters) {}
    /**
     * Lists Persons exclusively in the supplied tenant schema.
     *
     * @return LengthAwarePaginator<int, Person>
     */
    public function paginate(ConnectionInterface $connection, ?string $search, ?PersonType $personType, PersonStatus $status, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->matchingQuery($connection, $search, null, $personType, $status)
            ->withCount('contacts')
            ->orderBy('displayName')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Retrieves one lazy range. Included IDs remain in the result even when
     * they do not satisfy the active query, and are marked for the caller.
     *
     * @return array{people: Collection<int, Person>, total: int, matchedTotal: int, hiddenSelectedTotal: int, matchingIds: list<int>}
     */
    public function lazy(ConnectionInterface $connection, ?string $search, ?array $advancedFilter, ?PersonType $personType, PersonStatus $status, array $sorts, int $offset, int $limit, array $includeIds = [], bool $selectedOnly = false, array $selectedIds = []): array
    {
        $matching = $this->matchingQuery($connection, $search, $advancedFilter, $personType, $status);
        $matchedTotal = (clone $matching)->count();
        $included = $this->positiveUniqueIds($includeIds);
        $selection = $this->positiveUniqueIds($selectedIds);

        $query = (new Person)->forTenantConnection($connection)->newQuery();
        if ($selectedOnly) {
            $query->whereIn('id', $included);
        } elseif ($included === []) {
            $query->whereIn('id', (clone $matching)->select('id'));
        } else {
            $query->where(static function (Builder $builder) use ($matching, $included): void {
                $builder->whereIn('id', (clone $matching)->select('id'))->orWhereIn('id', $included);
            });
        }

        $total = (clone $query)->count();
        $this->applySort($query, $sorts);
        $people = $query->withCount('contacts')->offset($offset)->limit($limit)->get();
        $matchingIds = (clone $matching)->whereIn('id', $people->pluck('id'))->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $matchingSelectionIds = $selection === [] ? [] : (clone $matching)->whereIn('id', $selection)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $hiddenSelectedTotal = $selectedOnly ? 0 : count(array_diff($selection, $matchingSelectionIds));

        return compact('people', 'total', 'matchedTotal', 'hiddenSelectedTotal', 'matchingIds');
    }

    /** @return array{ids: list<int>, total: int, exceedsLimit: bool} */
    public function selectionIds(ConnectionInterface $connection, ?string $search, ?array $advancedFilter, ?PersonType $personType, PersonStatus $status, int $maximum = 10000): array
    {
        $query = $this->matchingQuery($connection, $search, $advancedFilter, $personType, $status);
        $total = (clone $query)->count();
        if ($total > $maximum) return ['ids' => [], 'total' => $total, 'exceedsLimit' => true];

        return ['ids' => $query->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all(), 'total' => $total, 'exceedsLimit' => false];
    }

    public function detail(ConnectionInterface $connection, int $personId): ?Person
    {
        return (new Person)->forTenantConnection($connection)->newQuery()
            ->with(['addresses', 'contacts', 'bankAccounts', 'pixKeys'])
            ->find($personId);
    }

    private function matchingQuery(ConnectionInterface $connection, ?string $search, ?array $advancedFilter, ?PersonType $personType, PersonStatus $status): Builder
    {
        $query = (new Person)->forTenantConnection($connection)->newQuery();
        if ($advancedFilter === null || ! $this->advancedFilters->usesField($advancedFilter, 'status')) $query->where('status', $status);
        if ($personType !== null) $query->where('personType', $personType);
        if ($search !== null) {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function (Builder $filter) use ($needle): void {
                $filter->where('name', 'like', $needle)->orWhere('displayName', 'like', $needle)->orWhere('alias', 'like', $needle)->orWhere('cpf', 'like', $needle)->orWhere('cnpj', 'like', $needle);
            });
        }
        if ($advancedFilter !== null) $this->advancedFilters->apply($query, $advancedFilter);
        return $query;
    }

    /** @param list<array{column: string, direction: string}> $sorts */
    private function applySort(Builder $query, array $sorts): void
    {
        foreach ($sorts as $sort) {
            $direction = strtolower($sort['direction']) === 'desc' ? 'desc' : 'asc';
            $column = match ($sort['column']) { 'personType' => 'personType', 'status' => 'status', 'document' => 'cpf', default => 'displayName' };
            $query->orderBy($column, $direction);
        }

        $query->orderBy('id');
    }

    /** @return list<int> */
    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
