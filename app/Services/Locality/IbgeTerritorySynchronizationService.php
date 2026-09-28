<?php

namespace App\Services\Locality;

use App\Infrastructure\Locality\IbgeTerritoryCatalog;
use App\Infrastructure\Locality\IbgeTerritorySource;
use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class IbgeTerritorySynchronizationService
{
    public function __construct(private readonly IbgeTerritorySource $source) {}

    public function synchronize(): IbgeTerritorySynchronizationResult
    {
        $startedAt = CarbonImmutable::now('America/Sao_Paulo');

        Log::info('locality.ibge-territory.synchronization.started', ['source' => 'ibge']);

        try {
            $catalog = $this->source->fetch();
            $counts = DB::transaction(fn (): array => $this->synchronizeCatalog($catalog));
        } catch (Throwable $exception) {
            $result = new IbgeTerritorySynchronizationResult(
                succeeded: false,
                startedAt: $startedAt,
                finishedAt: CarbonImmutable::now('America/Sao_Paulo'),
                createdCountryCount: 0,
                updatedCountryCount: 0,
                createdStateCount: 0,
                updatedStateCount: 0,
                createdMunicipalityCount: 0,
                updatedMunicipalityCount: 0,
                failureCode: $exception::class,
            );

            Log::warning('locality.ibge-territory.synchronization.failed', [
                'source' => 'ibge',
                'failureCode' => $result->failureCode,
            ]);

            return $result;
        }

        $result = new IbgeTerritorySynchronizationResult(
            succeeded: true,
            startedAt: $startedAt,
            finishedAt: CarbonImmutable::now('America/Sao_Paulo'),
            createdCountryCount: $counts['createdCountryCount'],
            updatedCountryCount: $counts['updatedCountryCount'],
            createdStateCount: $counts['createdStateCount'],
            updatedStateCount: $counts['updatedStateCount'],
            createdMunicipalityCount: $counts['createdMunicipalityCount'],
            updatedMunicipalityCount: $counts['updatedMunicipalityCount'],
        );

        Log::info('locality.ibge-territory.synchronization.completed', [
            'source' => 'ibge',
            ...$counts,
        ]);

        return $result;
    }

    /**
     * @return array{createdCountryCount: int, updatedCountryCount: int, createdStateCount: int, updatedStateCount: int, createdMunicipalityCount: int, updatedMunicipalityCount: int}
     */
    private function synchronizeCatalog(IbgeTerritoryCatalog $catalog): array
    {
        $country = Country::query()->firstOrNew(['isoAlpha2' => 'BR']);
        $createdCountryCount = $country->exists ? 0 : 1;
        $country->fill([
            'isoAlpha3' => 'BRA',
            'isoNumeric' => '076',
            'name' => 'Brasil',
            'activeForSelection' => true,
        ]);
        $country->save();

        $stateIdsByIbgeCode = [];
        $createdStateCount = 0;
        $updatedStateCount = 0;

        foreach ($catalog->states as $record) {
            $state = BrazilState::query()->firstOrNew(['ibgeCode' => $record->ibgeCode]);
            $state->exists ? $updatedStateCount++ : $createdStateCount++;
            $state->fill([
                'idCountry' => $country->id,
                'abbreviation' => $record->abbreviation,
                'name' => $record->name,
                'activeForSelection' => true,
            ]);
            $state->save();
            $stateIdsByIbgeCode[$record->ibgeCode] = $state->id;
        }

        $createdMunicipalityCount = 0;
        $updatedMunicipalityCount = 0;

        foreach ($catalog->municipalities as $record) {
            $stateId = $stateIdsByIbgeCode[$record->stateIbgeCode] ?? null;

            if ($stateId === null) {
                throw new RuntimeException('IBGE municipality refers to a state absent from the supplied catalog.');
            }

            $municipality = BrazilMunicipality::query()->firstOrNew(['ibgeCode' => $record->ibgeCode]);
            $municipality->exists ? $updatedMunicipalityCount++ : $createdMunicipalityCount++;
            $municipality->fill([
                'idBrazilState' => $stateId,
                'name' => $record->name,
                'activeForSelection' => true,
            ]);
            $municipality->save();
        }

        return [
            'createdCountryCount' => $createdCountryCount,
            'updatedCountryCount' => $createdCountryCount === 0 ? 1 : 0,
            'createdStateCount' => $createdStateCount,
            'updatedStateCount' => $updatedStateCount,
            'createdMunicipalityCount' => $createdMunicipalityCount,
            'updatedMunicipalityCount' => $updatedMunicipalityCount,
        ];
    }
}
