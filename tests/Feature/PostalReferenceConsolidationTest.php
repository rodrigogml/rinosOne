<?php

namespace Tests\Feature;

use App\Infrastructure\Locality\PostalReferenceSourceRecord;
use App\Models\BrazilMunicipality;
use App\Models\BrazilState;
use App\Models\Country;
use App\Models\LocalityReference;
use App\Models\LocalityReferenceObservation;
use App\Models\PostalCode;
use App\Services\Locality\PostalReferenceConsolidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostalReferenceConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciles_only_existing_ibge_municipality_and_is_idempotent(): void
    {
        $this->territory();
        $record = $this->record('VIA_CEP', 'first');
        $service = app(PostalReferenceConsolidationService::class);
        $service->consolidate([$record]);
        $service->consolidate([$record]);

        $reference = LocalityReference::sole();
        $this->assertNotNull($reference->idBrazilMunicipality);
        $this->assertSame(1, PostalCode::query()->count());
        $this->assertSame(1, LocalityReferenceObservation::query()->count());
    }

    public function test_unknown_ibge_code_preserves_observed_text_without_guessing_territory(): void
    {
        $this->territory();
        app(PostalReferenceConsolidationService::class)->consolidate([$this->record('VIA_CEP', 'unknown', '9999999')]);

        $reference = LocalityReference::sole();
        $this->assertNull($reference->idBrazilMunicipality);
        $this->assertSame('São Paulo', $reference->cityNameObserved);
        $this->assertSame('SP', $reference->stateCodeObserved);
    }

    public function test_strong_equivalence_removes_only_the_later_duplicate_and_never_reactivates_a_removed_record(): void
    {
        $this->territory();
        $service = app(PostalReferenceConsolidationService::class);
        $service->consolidate([$this->record('VIA_CEP', 'first'), $this->record('BRASIL_API', 'second')]);

        $references = LocalityReference::query()->orderBy('id')->get();
        $this->assertSame('ACTIVE', $references[0]->status);
        $this->assertSame('REMOVED', $references[1]->status);
        $this->assertSame('DUPLICATE_AUTOMATIC', $references[1]->removalReason);

        $service->consolidate([$this->record('BRASIL_API', 'second')]);
        $this->assertSame('REMOVED', $references[1]->fresh()->status);
    }

    private function territory(): void
    {
        $country = Country::query()->create(['isoAlpha2' => 'BR', 'isoAlpha3' => 'BRA', 'isoNumeric' => '076', 'name' => 'Brasil', 'activeForSelection' => true]);
        $state = BrazilState::query()->create(['idCountry' => $country->id, 'ibgeCode' => '35', 'abbreviation' => 'SP', 'name' => 'São Paulo', 'activeForSelection' => true]);
        BrazilMunicipality::query()->create(['idBrazilState' => $state->id, 'ibgeCode' => '3550308', 'name' => 'São Paulo', 'activeForSelection' => true]);
    }

    private function record(string $sourceKey, string $identity, string $ibgeCode = '3550308'): PostalReferenceSourceRecord
    {
        return new PostalReferenceSourceRecord($sourceKey, null, hash('sha256', $sourceKey.$identity), 'BR', '01001000', 'Praça da Sé', 'Sé', 'São Paulo', 'SP', $ibgeCode, ['streetName' => 'Praça da Sé', 'neighborhoodName' => 'Sé', 'municipalityName' => 'São Paulo', 'stateAbbreviation' => 'SP', 'municipalityIbgeCode' => $ibgeCode]);
    }
}
