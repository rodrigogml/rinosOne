<?php

namespace Tests\Feature;

use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use App\Models\LocalityReference;
use App\Models\PostalCode;
use App\Services\Locality\LocalityPostalCodeNormalizer;
use App\Services\Locality\LocalityReferenceQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalityReferenceQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_local_references_in_deterministic_order_without_inventing_a_street(): void
    {
        [$brazil, $state, $municipality] = $this->brazilianTerritory();
        $postalCode = PostalCode::query()->create([
            'idCountry' => $brazil->id,
            'normalizedValue' => '01001000',
            'displayValue' => '01001-000',
        ]);
        $municipalityReference = LocalityReference::query()->create([
            'idCountry' => $brazil->id,
            'idBrazilState' => $state->id,
            'idBrazilMunicipality' => $municipality->id,
            'localityKind' => 'MUNICIPALITY',
            'cityNameObserved' => 'São Paulo',
            'status' => 'ACTIVE',
        ]);
        $streetReference = LocalityReference::query()->create([
            'idCountry' => $brazil->id,
            'idBrazilState' => $state->id,
            'idBrazilMunicipality' => $municipality->id,
            'localityKind' => 'STREET',
            'streetType' => 'Praça',
            'streetName' => 'da Sé',
            'neighborhoodName' => 'Sé',
            'status' => 'ACTIVE',
        ]);
        $removedReference = LocalityReference::query()->create([
            'idCountry' => $brazil->id,
            'localityKind' => 'STREET',
            'streetName' => 'Referência removida',
            'status' => 'REMOVED',
            'removalReason' => 'INVALID',
            'removedAt' => now(),
        ]);
        $postalCode->localityReferences()->attach([$streetReference->id, $municipalityReference->id, $removedReference->id]);

        $results = $this->queryService()->findActiveByPostalCode(' br ', '01001-000');

        $this->assertSame([$municipalityReference->id, $streetReference->id], $results->modelKeys());
        $this->assertNull($results->first()->streetName);
        $this->assertTrue($results->first()->relationLoaded('brazilMunicipality'));
        $this->assertSame('SP', $results->first()->brazilMunicipality->brazilState->abbreviation);
        $this->assertTrue($results->last()->relationLoaded('postalCodes'));
        $this->assertSame([$postalCode->id], $results->last()->postalCodes->modelKeys());
    }

    public function test_keeps_identical_postal_values_isolated_by_country_and_returns_empty_for_unknown_local_data(): void
    {
        $brazil = Country::query()->create($this->countryAttributes());
        $unitedStates = Country::query()->create($this->countryAttributes([
            'isoAlpha2' => 'US',
            'isoAlpha3' => 'USA',
            'isoNumeric' => '840',
            'name' => 'Estados Unidos',
        ]));
        $foreignPostalCode = PostalCode::query()->create([
            'idCountry' => $unitedStates->id,
            'normalizedValue' => '01001000',
            'displayValue' => '01001-000',
        ]);
        $foreignReference = LocalityReference::query()->create([
            'idCountry' => $unitedStates->id,
            'localityKind' => 'UNSPECIFIED',
            'status' => 'ACTIVE',
        ]);
        $foreignPostalCode->localityReferences()->attach($foreignReference->id);

        $this->assertTrue($this->queryService()->findActiveByPostalCode('BR', '01001-000')->isEmpty());
        $this->assertSame([$foreignReference->id], $this->queryService()->findActiveByPostalCode('US', '01001-000')->modelKeys());
        $this->assertTrue($this->queryService()->findActiveByPostalCode('BR', '99999-999')->isEmpty());
        $this->assertSame($brazil->id, Country::query()->where('isoAlpha2', 'BR')->value('id'));
    }

    /**
     * @return array{0: Country, 1: BrazilState, 2: BrazilMunicipality}
     */
    private function brazilianTerritory(): array
    {
        $country = Country::query()->create($this->countryAttributes());
        $state = BrazilState::query()->create([
            'idCountry' => $country->id,
            'ibgeCode' => '35',
            'abbreviation' => 'SP',
            'name' => 'São Paulo',
            'activeForSelection' => true,
        ]);
        $municipality = BrazilMunicipality::query()->create([
            'idBrazilState' => $state->id,
            'ibgeCode' => '3550308',
            'name' => 'São Paulo',
            'activeForSelection' => true,
        ]);

        return [$country, $state, $municipality];
    }

    /**
     * @param  array<string, string|bool>  $overrides
     * @return array<string, string|bool>
     */
    private function countryAttributes(array $overrides = []): array
    {
        return array_merge([
            'isoAlpha2' => 'BR',
            'isoAlpha3' => 'BRA',
            'isoNumeric' => '076',
            'name' => 'Brasil',
            'activeForSelection' => true,
        ], $overrides);
    }

    private function queryService(): LocalityReferenceQueryService
    {
        return new LocalityReferenceQueryService(new LocalityPostalCodeNormalizer);
    }
}
