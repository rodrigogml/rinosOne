<?php

namespace App\Services\Person;

use App\Domain\Person\PersonStatus;
use App\Domain\Person\PersonType;
use App\Models\Person;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;

class PersonQueryService
{
    /**
     * Lists Persons exclusively in the supplied tenant schema.
     *
     * @return LengthAwarePaginator<int, Person>
     */
    public function paginate(ConnectionInterface $connection, ?string $search, ?PersonType $personType, PersonStatus $status, int $page, int $perPage): LengthAwarePaginator
    {
        $query = (new Person)->forTenantConnection($connection)->newQuery()->where('status', $status);

        if ($personType !== null) {
            $query->where('personType', $personType);
        }
        if ($search !== null) {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($filter) use ($needle): void {
                $filter->where('name', 'like', $needle)
                    ->orWhere('displayName', 'like', $needle)
                    ->orWhere('alias', 'like', $needle)
                    ->orWhere('cpf', 'like', $needle)
                    ->orWhere('cnpj', 'like', $needle)
                    ->orWhereHas('contacts', fn ($contacts) => $contacts->where('normalizedValue', 'like', $needle));
            });
        }

        return $query
            ->withCount('contacts')
            ->orderBy('displayName')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function detail(ConnectionInterface $connection, int $personId): ?Person
    {
        return (new Person)->forTenantConnection($connection)->newQuery()
            ->with(['addresses', 'contacts', 'bankAccounts', 'pixKeys'])
            ->find($personId);
    }
}
