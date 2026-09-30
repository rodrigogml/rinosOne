<?php

namespace App\Services\Person;

use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use App\Models\FinancialInstitution;

/** Provides safe, active-only core catalog projections used by People editors. */
class PersonReferenceCatalogQuery
{
    /** @return list<array{id:int,isoAlpha2:string,name:string}> */
    public function countries(): array
    {
        return Country::query()->where('activeForSelection', true)->orderBy('name')->get(['id', 'isoAlpha2', 'name'])
            ->map(fn (Country $country): array => ['id' => $country->id, 'isoAlpha2' => $country->isoAlpha2, 'name' => $country->name])->all();
    }

    /** @return list<array{id:int,abbreviation:string,name:string}> */
    public function brazilStates(int $countryId): array
    {
        return BrazilState::query()->where('idCountry', $countryId)->where('activeForSelection', true)->orderBy('name')->get(['id', 'abbreviation', 'name'])
            ->map(fn (BrazilState $state): array => ['id' => $state->id, 'abbreviation' => $state->abbreviation, 'name' => $state->name])->all();
    }

    /** @return list<array{id:int,name:string}> */
    public function brazilMunicipalities(int $stateId): array
    {
        return BrazilMunicipality::query()->where('idBrazilState', $stateId)->where('activeForSelection', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (BrazilMunicipality $municipality): array => ['id' => $municipality->id, 'name' => $municipality->name])->all();
    }

    /** @return list<array{id:int,name:string,code:?string}> */
    public function financialInstitutions(?string $search): array
    {
        $search = trim((string) $search);

        return FinancialInstitution::query()->where('activeForSelection', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($items) => $items->where('legalName', 'like', "%{$search}%")->orWhere('reducedName', 'like', "%{$search}%")->orWhere('compeCode', 'like', "%{$search}%")))
            ->orderBy('legalName')->limit(50)->get(['id', 'legalName', 'reducedName', 'compeCode'])
            ->map(fn (FinancialInstitution $institution): array => ['id' => $institution->id, 'name' => $institution->reducedName ?: $institution->legalName, 'code' => $institution->compeCode])->all();
    }
}
